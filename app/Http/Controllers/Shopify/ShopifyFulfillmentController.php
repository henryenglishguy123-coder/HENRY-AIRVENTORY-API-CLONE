<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Traits\Shopify\VerifiesShopifySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyFulfillmentController extends Controller
{
    use VerifiesShopifySignature;

    /**
     * Handle Shopify fulfillment service callbacks.
     * This endpoint is required for the fulfillment service registration.
     */
    public function callback(Request $request): JsonResponse
    {
        // 1. Validate Signature
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->getContent();
        $payload = json_decode($data, true);

        Log::info('Shopify fulfillment callback received', [
            'payload' => $payload,
            'headers' => $request->headers->all(),
        ]);

        // Basic validation
        if (! isset($payload['kind'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // TODO: Implement specific action handling
        return response()->json(['success' => true], 200);
    }
}
