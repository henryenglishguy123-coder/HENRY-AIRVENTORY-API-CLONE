<?php

namespace App\Services\Customer\Payments;

use App\Models\Customer\Vendor;
use Stripe\Customer;
use Stripe\Stripe;

class StripeCustomerService
{
    public static function getOrCreate(Vendor $vendor, string $secretKey): string
    {
        Stripe::setApiKey($secretKey);

        if ($vendor->gateway_customer_id) {
            return $vendor->gateway_customer_id;
        }

        $customer = Customer::create([
            'email' => $vendor->email,
            'name' => trim($vendor->first_name.' '.$vendor->last_name),
            'metadata' => [
                'vendor_id' => $vendor->id,
            ],
        ]);

        $vendor->update([
            'gateway_customer_id' => $customer->id,
        ]);

        return $customer->id;
    }
}
