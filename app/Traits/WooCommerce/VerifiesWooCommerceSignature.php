<?php

declare(strict_types=1);

namespace App\Traits\WooCommerce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait VerifiesWooCommerceSignature
{
    /**
     * Verify the WooCommerce HMAC signature.
     */
    protected function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-WC-Webhook-Signature');

        if (empty($signature)) {
            Log::warning('WooCommerce Webhook: Missing signature header');

            return false;
        }

        if (empty($secret)) {
            Log::error('WooCommerce Webhook: Missing secret for verification');

            return false;
        }

        $payload = $request->getContent();
        $calculated = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (! hash_equals($calculated, $signature)) {
            Log::warning('WooCommerce Webhook: Invalid signature', [
                'provided' => $signature,
            ]);

            return false;
        }

        return true;
    }
}
