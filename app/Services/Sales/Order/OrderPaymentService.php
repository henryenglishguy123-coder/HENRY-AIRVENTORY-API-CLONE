<?php

namespace App\Services\Sales\Order;

use App\Models\Sales\Order\Payment\SalesOrderPayment;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Customer\Payments\PaymentGatewayManager;
use App\Services\Customer\Wallet\WalletService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPaymentService
{
    public function processPayment(Collection|SalesOrder $orders, array $data): array
    {
        $orders = $orders instanceof SalesOrder ? collect([$orders]) : $orders;

        $this->reconcilePendingPayments($orders);

        /** ------------------------------------------------
         * 1. Prepare Orders & Remaining Amounts
         * ------------------------------------------------ */
        $customerId = null;
        $ordersToPay = [];
        $totalRemainingAmount = 0.00;

        foreach ($orders as $order) {
            if ($order->payment_status === 'paid') {
                continue;
            }

            $customerId ??= $order->customer_id;

            if ($customerId !== $order->customer_id) {
                throw new Exception(__('All orders must belong to the same customer.'));
            }

            $alreadyPaid = $order->payments()
                ->where('payment_status', 'paid')
                ->sum('amount');

            $remaining = round($order->grand_total_inc_margin - $alreadyPaid, 2);

            if ($remaining > 0) {
                $ordersToPay[] = [
                    'order' => $order,
                    'remaining' => $remaining,
                ];
                $totalRemainingAmount += $remaining;
            }
        }

        if (empty($ordersToPay)) {
            throw new Exception(__('All selected orders are already paid.'));
        }

        $totalRemainingAmount = round($totalRemainingAmount, 2);

        $useWallet = ! empty($data['use_wallet']);
        $paymentMethod = $data['payment_method'] ?? null;
        $paymentMethodId = $data['payment_method_id'] ?? null;

        DB::beginTransaction();

        try {
            /** ------------------------------------------------
             * 2. Wallet Payment (if enabled)
             * ------------------------------------------------ */
            if ($useWallet && $totalRemainingAmount > 0) {
                $walletBalance = WalletService::getBalance($customerId);

                if ($walletBalance > 0) {
                    $walletAmount = round(min($walletBalance, $totalRemainingAmount), 2);

                    if ($walletAmount > 0) {
                        $orderNumbers = collect($ordersToPay)
                            ->pluck('order.order_number')
                            ->join(', ');

                        WalletService::debit(
                            vendorId: $customerId,
                            amount: $walletAmount,
                            description: Str::limit(
                                __('Payment for Orders: :orders', ['orders' => $orderNumbers]),
                                250
                            ),
                            reference: 'bulk_wallet_'.Str::uuid()
                        );

                        $this->distributePayment(
                            $ordersToPay,
                            $walletAmount,
                            fn ($order, $amount) => $this->createPayment(
                                $order,
                                $customerId,
                                'wallet_'.Str::uuid(),
                                'wallet',
                                'wallet',
                                $amount,
                                'Paid via Wallet'
                            )
                        );

                        $totalRemainingAmount = round($totalRemainingAmount - $walletAmount, 2);
                    }
                }
            }

            /** ------------------------------------------------
             * 3. External Gateway Payment
             * ------------------------------------------------ */
            $charge = null;

            if ($totalRemainingAmount > 0) {
                if (! $paymentMethod || ! $paymentMethodId) {
                    throw new Exception(__('Payment method is required for the remaining amount.'));
                }

                $gateway = PaymentGatewayManager::resolve($paymentMethod);

                $charge = $gateway->chargeSavedMethod(
                    vendorId: $customerId,
                    payment_method_id: $paymentMethodId,
                    amount: $totalRemainingAmount,
                    metadata: [
                        'type' => 'order_payment',
                        'order_numbers' => collect($ordersToPay)->pluck('order.order_number')->join(', '),
                        'is_bulk' => count($ordersToPay) > 1,
                    ]
                );

                if (empty($charge['success']) && empty($charge['requires_action'])) {
                    throw new Exception($charge['message'] ?? __('Payment failed.'));
                }

                $paymentStatus = ! empty($charge['requires_action']) ? 'pending' : 'paid';
                $transactionId = $charge['payment_intent_id'] ?? $charge['id'] ?? null;

                if (! $transactionId) {
                    throw new Exception(__('Payment processed but transaction ID missing.'));
                }

                $this->distributePayment(
                    $ordersToPay,
                    $totalRemainingAmount,
                    fn ($order, $amount) => $this->createPayment(
                        $order,
                        $customerId,
                        $transactionId,
                        $paymentMethod,
                        $paymentMethod,
                        $amount,
                        'Paid via '.ucfirst($paymentMethod),
                        $charge,
                        $paymentStatus
                    )
                );
            }

            /** ------------------------------------------------
             * 4. Mark Orders Paid (only if no action required)
             * ------------------------------------------------ */
            if (! $charge || empty($charge['requires_action'])) {
                $finalMethod = $charge ? $paymentMethod : 'wallet';

                foreach ($ordersToPay as $item) {
                    $order = $item['order'];
                    $order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                        'payment_method' => $finalMethod,
                    ]);
                }
            }

            DB::commit();

            if ($charge && ! empty($charge['requires_action'])) {
                return [
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $charge['client_secret'],
                    'payment_intent_id' => $charge['payment_intent_id'],
                    'message' => __('Payment requires authentication'),
                ];
            }

            return [
                'success' => true,
                'message' => __('Payment successful'),
                'data' => [
                    'order_ids' => collect($ordersToPay)->pluck('order.id'),
                    'payment_status' => 'paid',
                ],
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** ------------------------------------------------
     * Helpers
     * ------------------------------------------------ */
    private function distributePayment(array &$orders, float $amount, callable $callback): void
    {
        $remaining = $amount;

        foreach ($orders as &$item) {
            if ($remaining <= 0 || $item['remaining'] <= 0) {
                continue;
            }

            $apply = min($item['remaining'], $remaining);

            $callback($item['order'], $apply);

            $item['remaining'] = round($item['remaining'] - $apply, 2);
            $remaining = round($remaining - $apply, 2);
        }
    }

    private function createPayment(
        SalesOrder $order,
        int $customerId,
        string $transactionId,
        string $method,
        string $gateway,
        float $amount,
        string $notes,
        ?array $gatewayResponse = null,
        string $status = 'paid'
    ): void {
        SalesOrderPayment::create([
            'vendor_id' => $customerId,
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'payment_method' => $method,
            'gateway' => $gateway,
            'payment_status' => $status,
            'currency_code' => $order->currency_code ?? config('app.currency', 'USD'),
            'amount' => $amount,
            'gateway_response' => $gatewayResponse,
            'paid_at' => $status === 'paid' ? now() : null,
            'notes' => $notes,
        ]);
    }

    private function reconcilePendingPayments(Collection $orders): void
    {
        $orderIds = $orders->pluck('id')->all();

        if (empty($orderIds)) {
            return;
        }

        $pendingPayments = SalesOrderPayment::query()
            ->whereIn('order_id', $orderIds)
            ->where('payment_status', 'pending')
            ->get();

        if ($pendingPayments->isEmpty()) {
            return;
        }

        $ordersById = $orders->keyBy('id');

        $byGateway = $pendingPayments->groupBy('gateway');

        foreach ($byGateway as $gateway => $payments) {
            if ($gateway !== 'stripe') {
                continue;
            }

            $stripeGateway = PaymentGatewayManager::resolve('stripe');

            $byTransaction = $payments->groupBy('transaction_id');

            foreach ($byTransaction as $transactionId => $transactionPayments) {
                if (! $transactionId) {
                    continue;
                }

                $statusInfo = $stripeGateway->getPaymentIntentStatus($transactionId);
                $status = $statusInfo['status'] ?? null;

                if (! $status) {
                    continue;
                }

                if ($status === 'succeeded') {
                    foreach ($transactionPayments as $payment) {
                        $payment->update([
                            'payment_status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    }

                    foreach ($transactionPayments as $payment) {
                        $order = $ordersById->get($payment->order_id);

                        if (! $order) {
                            continue;
                        }

                        $totalPaid = $order->payments()
                            ->where('payment_status', 'paid')
                            ->sum('amount');

                        if ($totalPaid >= $order->grand_total_inc_margin) {
                            $order->update([
                                'payment_status' => 'paid',
                                'order_status' => 'confirmed',
                            ]);

                            if ($order->customer_id) {
                                Cache::put("orders_version:customer_{$order->customer_id}", time());
                            }

                            Cache::put('orders_version:admin_global', time());
                        }
                    }
                } elseif (in_array($status, ['canceled', 'requires_payment_method'], true)) {
                    foreach ($transactionPayments as $payment) {
                        $payment->update([
                            'payment_status' => 'failed',
                        ]);
                    }
                } elseif (in_array($status, ['requires_action', 'processing', 'requires_confirmation'], true)) {
                    throw new Exception(__('A previous card payment is still in progress. Please wait before trying again.'));
                }
            }
        }
    }
}
