<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Callbacks;

use App\Http\Controllers\Controller;
use App\Jobs\Shopify\RegisterShopifyFulfillmentServiceJob;
use App\Jobs\Shopify\RegisterShopifyWebhooksJob;
use App\Jobs\WooCommerce\RegisterWooCommerceWebhooksJob;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Store\StoreConnectionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StoreCallbackController extends Controller
{
    public function __construct(
        private readonly StoreConnectorFactory $factory,
        private readonly StoreConnectionService $connectionService,
    ) {}

    /**
     * Handle the install callback from store channels.
     */
    public function installed(Request $request, string $channel): JsonResponse|RedirectResponse
    {
        Log::info("Received install callback for channel: {$channel}", $request->except(['access_token', 'token', 'oauth_token', 'api_key', 'secret', 'password', 'code']));

        try {
            $storeChannel = StoreChannel::whereCode($channel)->firstOrFail();
            $connector = $this->factory->make($storeChannel);

            // 1. Validate Callback & Get Vendor ID
            $vendorId = $connector->validateInstallCallback($request);

            if (! $vendorId) {
                return $this->errorResponse(
                    $request,
                    __('Invalid or expired callback session. Please try again.'),
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // 2. Normalize Payload & Connect Store
            $data = $connector->normalizeInstallPayload($request->all());
            $data['vendor_id'] = $vendorId;

            // Connect using service (handles transaction & DB storage)
            $store = $this->connectionService->connect($data, $channel);

            // 3. Verify Credentials (Post-Connect Check)
            try {
                // Decrypt token to pass to verify method
                // We assume token is always an encrypted array as per service contract
                $credentials = decrypt($store->token);
                if (! is_array($credentials)) {
                    $store->markError(__('Invalid store credentials format'));

                    return $this->errorResponse(
                        $request,
                        __('Invalid store credentials format.'),
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                // Add link for context if needed
                $credentials['link'] = $store->link;

                if (! $connector->verify($credentials)) {
                    $store->markError(__('Unable to verify store credentials'));

                    return $this->errorResponse(
                        $request,
                        __('Unable to verify store credentials.'),
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                // 4. Post-Connection Actions (e.g. Webhook Registration)
                $this->handlePostConnectionActions($channel, $store, $credentials);
            } catch (\Exception $e) {
                Log::warning('Token decryption or verification failed', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
                // Verification failures are treated as fatal errors for the connection process.
                // We mark the store as errored and return an error response.
                $store->markError(__('Token verification failed'));

                return $this->errorResponse(
                    $request,
                    __('Store connected but verification failed.'),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            return $this->successResponse($request, $store);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($request, __('Store channel not found.'), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            Log::error('Unsupported store channel or invalid payload', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($request, __('Invalid request parameters.'), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            Log::error('Error in store callback', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                $request,
                __('An unexpected error occurred while connecting the store.'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Generate success response based on request type.
     */
    private function successResponse(Request $request, $store): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Store connected successfully'),
                'data' => [
                    'id' => $store->id,
                    'store' => $store->link,
                    'status' => $store->status_label,
                ],
            ]);
        }
        $redirectUrl = config('app.customer_panel_url').'/stores?status=success&store_id='.$store->id;

        return redirect()->away($redirectUrl);
    }

    /**
     * Generate error response based on request type.
     */
    private function errorResponse(Request $request, string $message, int $statusCode): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }

        $redirectUrl = config('app.customer_panel_url').'/stores?status=error&message='.urlencode($message);

        return redirect()->away($redirectUrl);
    }

    private function handlePostConnectionActions(string $channel, $store, array $credentials): void
    {
        try {
            if ($channel === 'shopify') {
                RegisterShopifyWebhooksJob::dispatch($store->store_identifier);
                RegisterShopifyFulfillmentServiceJob::dispatch($store->store_identifier);
                Log::info('Dispatched Shopify webhook and fulfillment registration jobs', ['store_id' => $store->id]);
            } elseif ($channel === 'woocommerce') {
                RegisterWooCommerceWebhooksJob::dispatch($store->id);
                Log::info('Dispatched WooCommerce webhook registration job', ['store_id' => $store->id]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to execute post-connection actions', [
                'channel' => $channel,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
