<?php

namespace App\Services\Customer\Payments\Gateways;

use App\Models\Currency\Currency;
use App\Models\Customer\Payment\VendorSavedPaymentMethod;
use App\Models\Customer\Vendor;
use App\Models\Payment\PaymentSetting;
use App\Services\Customer\Payments\Contracts\GatewayInterface;
use App\Services\Customer\Payments\StripeCustomerService;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;

class StripeGateway implements GatewayInterface
{
    protected PaymentSetting $setting;

    public function __construct(PaymentSetting $setting)
    {
        $this->setting = $setting;
        Stripe::setApiKey($this->setting->app_secret);
    }

    public function createSetupIntent(int $vendorId)
    {
        $vendor = Vendor::findOrFail($vendorId);
        $customerId = StripeCustomerService::getOrCreate(
            $vendor,
            $this->setting->app_secret
        );
        try {
            return SetupIntent::create(
                [
                    'customer' => $customerId,
                    'usage' => 'off_session',
                    'payment_method_types' => ['card'],
                    'metadata' => [
                        'vendor_id' => $vendorId,
                        'purpose' => 'save_card',
                    ],
                ],
                [
                    'idempotency_key' => 'setup_'.$vendorId.'_'.Str::uuid(),
                ]
            );
        } catch (ApiErrorException $e) {
            report($e->getMessage());
            throw new \RuntimeException(__('Unable to initiate card setup.'));
        }
    }

    public function saveMethod(array $data)
    {
        $vendor = Vendor::findOrFail($data['vendor_id']);
        $customerId = StripeCustomerService::getOrCreate(
            $vendor,
            $this->setting->app_secret
        );
        $pm = PaymentMethod::retrieve($data['payment_method_id']);
        $pm->attach([
            'customer' => $customerId,
        ]);

        return VendorSavedPaymentMethod::create([
            'vendor_id' => $vendor->id,
            'payment_method' => 'stripe',
            'saved_card_id' => $pm->id,
            'card_type' => $pm->card->brand ?? null,
            'card_last_digit' => $pm->card->last4 ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    public function listMethods(int $vendorId)
    {
        return VendorSavedPaymentMethod::where('vendor_id', $vendorId)
            ->where('payment_method', 'stripe')
            ->get();
    }

    public function deleteMethod(int $vendorId, int $methodId)
    {
        $method = VendorSavedPaymentMethod::query()
            ->where('id', $methodId)
            ->where('vendor_id', $vendorId)
            ->where('payment_method', 'stripe')
            ->firstOrFail();
        try {
            $pm = PaymentMethod::retrieve($method->saved_card_id);
            $pm->detach();
        } catch (\Throwable $e) {
        }
        $wasDefault = $method->is_default;
        $method->delete();
        if ($wasDefault) {
            $newDefault = VendorSavedPaymentMethod::query()
                ->where('vendor_id', $vendorId)
                ->where('payment_method', 'stripe')
                ->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return true;
    }

    public function chargeSavedMethod(int $vendorId, string $payment_method_id, float $amount, array $metadata = [])
    {
        $currency = strtolower(Currency::getDefaultCurrency()->code);
        $vendor = Vendor::findOrFail($vendorId);
        $customerId = StripeCustomerService::getOrCreate(
            $vendor,
            $this->setting->app_secret
        );

        $defaultMetadata = [
            'vendor_id' => $vendorId,
            'type' => 'wallet_topup',
        ];

        try {
            $intent = PaymentIntent::create(
                [
                    'amount' => (int) round($amount * 100),
                    'currency' => $currency,
                    'customer' => $customerId,
                    'payment_method' => $payment_method_id,
                    'confirmation_method' => 'automatic',
                    'confirm' => true,
                    'payment_method_types' => ['card'],
                    'metadata' => array_merge($defaultMetadata, $metadata),
                ]
            );
            if ($intent->status === 'requires_action') {
                return [
                    'requires_action' => true,
                    'client_secret' => $intent->client_secret,
                    'payment_intent_id' => $intent->id,
                ];
            }
            if ($intent->status !== 'succeeded') {
                throw new \RuntimeException('Payment failed: '.$intent->status);
            }

            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
            ];
        } catch (CardException $e) {
            return [
                'success' => false,
                'message' => $e->getError()->message ?? __('Card declined'),
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(__('Payment service unavailable.'));
        }
    }

    public function confirmPaymentIntent(int $vendorId, string $paymentIntentId): array
    {
        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);
            if (
                empty($intent->metadata->vendor_id) ||
                (int) $intent->metadata->vendor_id !== $vendorId ||
                ($intent->metadata->type ?? null) !== 'wallet_topup'
            ) {
                throw new \RuntimeException('Invalid payment intent metadata.');
            }
            if ($intent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'status' => $intent->status,
                    'message' => 'Payment not completed yet.',
                ];
            }

            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'amount' => $intent->amount_received / 100,
                'currency' => strtoupper($intent->currency),
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(__('Unable to verify payment.'));
        }
    }

    public function getPaymentIntentStatus(string $paymentIntentId): array
    {
        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);

            return [
                'status' => $intent->status,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException(__('Unable to verify payment status.'));
        }
    }
}
