<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Traits\Shopify\VerifiesShopifySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyGdprController extends Controller
{
    use VerifiesShopifySignature;

    /**
     * Handle Customer Data Request (View Data)
     *
     * @see https://shopify.dev/docs/apps/store/data-protection/processing-data-requests
     */
    public function customersDataRequest(Request $request): JsonResponse
    {
        return $this->handleGdprRequest($request, 'customers/data_request');
    }

    /**
     * Handle Customer Redact Request (Erase Data)
     *
     * @see https://shopify.dev/docs/apps/store/data-protection/processing-data-requests
     */
    public function customersRedact(Request $request): JsonResponse
    {
        return $this->handleGdprRequest($request, 'customers/redact');
    }

    /**
     * Handle Shop Redact Request (Erase Shop Data)
     *
     * @see https://shopify.dev/docs/apps/store/data-protection/processing-data-requests
     */
    public function shopRedact(Request $request): JsonResponse
    {
        return $this->handleGdprRequest($request, 'shop/redact');
    }

    /**
     * Verify and log the GDPR request.
     */
    private function handleGdprRequest(Request $request, string $topic): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Log the request metadata for audit/processing (avoiding PII in payload)
        Log::info("Shopify GDPR Request Received: {$topic}", [
            'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
            'shop_id' => $request->input('shop_id'),
        ]);

        // TODO: Implement actual data retrieval/deletion logic here based on your app's requirements.
        // For now, we acknowledge receipt as per Shopify requirements.

        return response()->json([], 200);
    }
}
