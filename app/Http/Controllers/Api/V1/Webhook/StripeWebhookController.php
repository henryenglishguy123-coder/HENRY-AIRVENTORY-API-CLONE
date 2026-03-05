<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentSetting;
use App\Models\Sales\Order\Payment\SalesOrderPayment;
use App\Services\Customer\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $setting = PaymentSetting::where('payment_method', 'stripe')->first();
        if (! $setting || ! $setting->is_active) {
            Log::warning('Stripe webhook received but service is inactive');

            return response()->json(['message' => 'Service unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $endpointSecret = config('services.stripe.webhook_secret');
        if (empty($endpointSecret)) {
            return response()->json(['message' => 'Webhook secret not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_BAD_REQUEST);
        }
        // Handle the event
        Log::info('Stripe Webhook Received: '.$event->type, ['id' => $event->id]);
        $result = ['status' => 'success'];
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $action = $this->handlePaymentIntentSucceeded($paymentIntent);
                $result['action'] = $action;
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $action = $this->handlePaymentIntentFailed($paymentIntent);
                $result['action'] = $action;
                break;
            default:
                // Unexpected event type
                $result['status'] = 'ignored';
                break;
        }

        return response()->json($result);
    }

    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        try {
            Log::info('Processing Payment Intent Success', [
                'id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount,
                'metadata' => $paymentIntent->metadata->toArray(),
            ]);

            if (isset($paymentIntent->metadata->type) && $paymentIntent->metadata->type === 'order_payment') {
                // Use a transaction for atomicity
                \Illuminate\Support\Facades\DB::transaction(function () use ($paymentIntent) {
                    $transactions = SalesOrderPayment::where('transaction_id', $paymentIntent->id)->get();

                    Log::info('Found order payment transactions', [
                        'payment_intent_id' => $paymentIntent->id,
                        'count' => $transactions->count(),
                    ]);

                    if ($transactions->isNotEmpty()) {
                        $ordersToUpdate = [];

                        foreach ($transactions as $transaction) {
                            if ($transaction->payment_status !== 'paid') {
                                $transaction->update([
                                    'payment_status' => 'paid',
                                    'paid_at' => now(),
                                ]);
                                Log::info('Transaction marked as paid', ['transaction_id' => $transaction->id, 'order_id' => $transaction->order_id]);
                                $ordersToUpdate[$transaction->order_id] = $transaction->order;
                            }
                        }

                        // Update orders after all payments are marked completed
                        foreach ($ordersToUpdate as $order) {
                            $totalPaid = $order->payments()->where('payment_status', 'paid')->sum('amount');

                            if ($totalPaid >= $order->grand_total_inc_margin) {
                                $transaction = $transactions->firstWhere('order_id', $order->id);
                                $method = $transaction ? $transaction->payment_method : 'stripe';

                                $order->update([
                                    'payment_status' => 'paid',
                                    'order_status' => 'confirmed',
                                    'payment_method' => $method,
                                ]);
                                Log::info('Order status updated to processing', ['order_id' => $order->id]);

                                // Invalidate order list cache for the customer
                                Cache::put("orders_version:customer_{$order->customer_id}", time());
                            }
                        }
                        Log::info('Order payments confirmed for Payment Intent', ['id' => $paymentIntent->id, 'count' => $transactions->count()]);
                    } else {
                        Log::warning('Order payment transaction not found for Payment Intent', ['id' => $paymentIntent->id]);
                    }
                });

                // Check if transactions existed (we can't easily return from inside transaction closure to outside method scope
                // to control the flow exactly as before, but the logic below assumes if it wasn't order_payment it would go to wallet.
                // However, we are inside the if (metadata->type === 'order_payment') block.
                // So we should just return here.

                $transactionsExist = SalesOrderPayment::where('transaction_id', $paymentIntent->id)->exists();
                if ($transactionsExist) {
                    return 'order_payment_confirmed';
                } else {
                    return 'order_transaction_not_found';
                }
            }

            $wallet = WalletService::confirmCredit($paymentIntent->id);
            if ($wallet) {
                Log::info('Wallet credited for Payment Intent', [
                    'payment_intent_id' => $paymentIntent->id,
                    'wallet_id' => $wallet->id,
                    'vendor_id' => $wallet->vendor_id,
                    'new_balance' => $wallet->balance,
                ]);

                return 'credited';
            } else {
                if (isset($paymentIntent->metadata->vendor_id) &&
                    isset($paymentIntent->metadata->type) &&
                    $paymentIntent->metadata->type === 'wallet_topup') {
                    Log::info('Transaction missing, creating from metadata for Payment Intent', ['id' => $paymentIntent->id]);
                    WalletService::initiateCredit(
                        vendorId: (int) $paymentIntent->metadata->vendor_id,
                        amount: $paymentIntent->amount / 100, // Amount is in cents
                        paymentMethod: 'stripe',
                        description: __('Wallet top-up via Stripe'),
                        reference: $paymentIntent->id
                    );
                    $wallet = WalletService::confirmCredit($paymentIntent->id);
                    if ($wallet) {
                        Log::info('Wallet credited after creation for Payment Intent', [
                            'id' => $paymentIntent->id,
                            'wallet_id' => $wallet->id,
                            'vendor_id' => $wallet->vendor_id,
                        ]);

                        return 'created_and_credited';
                    }
                }
                Log::warning('Transaction not found or already processed for Payment Intent', ['id' => $paymentIntent->id]);

                return 'not_processed';
            }
        } catch (\Exception $e) {
            Log::error('Error processing Payment Intent Success: '.$e->getMessage(), [
                'payment_intent_id' => $paymentIntent->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to let Stripe retry
        }
    }

    protected function handlePaymentIntentFailed($paymentIntent)
    {
        try {
            Log::info('Processing Payment Intent Failure', ['id' => $paymentIntent->id]);

            if (isset($paymentIntent->metadata->type) && $paymentIntent->metadata->type === 'order_payment') {
                $transactions = SalesOrderPayment::where('transaction_id', $paymentIntent->id)->get();
                if ($transactions->isNotEmpty()) {
                    foreach ($transactions as $transaction) {
                        $transaction->update(['payment_status' => 'failed']);
                        Log::info('Transaction marked as failed', ['transaction_id' => $transaction->id, 'order_id' => $transaction->order_id]);
                    }
                    Log::info('Order payments marked as failed for Payment Intent', ['id' => $paymentIntent->id]);

                    return 'order_payment_failed';
                }

                // Early return if not found to avoid wallet fail path
                Log::warning('Order payment transaction not found for Payment Intent', ['id' => $paymentIntent->id]);

                return 'order_payment_not_found';
            }

            WalletService::failCredit($paymentIntent->id);
            Log::info('Wallet transaction marked as failed', ['payment_intent_id' => $paymentIntent->id]);

            return 'failed';
        } catch (\Exception $e) {
            Log::error('Error processing Payment Intent Failure: '.$e->getMessage(), [
                'payment_intent_id' => $paymentIntent->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to let Stripe retry for consistency
        }
    }
}
