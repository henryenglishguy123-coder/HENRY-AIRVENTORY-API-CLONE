<?php

namespace App\Services\Customer\Payments;

use App\Models\Payment\PaymentSetting;
use App\Services\Customer\Payments\Gateways\StripeGateway;
use Exception;

class PaymentGatewayManager
{
    public static function resolve(string $paymentMethod)
    {
        $setting = PaymentSetting::query()
            ->where('payment_method', $paymentMethod)
            ->where('is_active', true)
            ->first();
        if (! $setting) {
            throw new Exception(__('Payment method not available'));
        }

        return match ($paymentMethod) {
            'stripe' => new StripeGateway($setting),
            default => throw new Exception(__('Unsupported payment method')),
        };
    }
}
