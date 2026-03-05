<?php

declare(strict_types=1);

namespace App\Traits\Shopify;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait VerifiesShopifySignature
{
    /**
     * Verify the Shopify HMAC signature.
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Shopify-Hmac-Sha256');
        $secret = config('services.shopify.secret');

        if (empty($secret)) {
            Log::error('Shopify secret is not configured (services.shopify.secret).');

            return false;
        }

        if (empty($signature)) {
            return false;
        }

        $data = $request->getContent();
        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));

        return hash_equals($calculatedHmac, $signature);
    }
}
