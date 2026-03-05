<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\Shopify\ShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterShopifyFulfillmentServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int[]
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $storeIdentifier
    ) {
        $this->onQueue('integrations');
    }

    /**
     * Execute the job.
     */
    public function handle(ShopifyFulfillmentService $service): void
    {
        Log::info('Starting Shopify fulfillment service registration', ['store_identifier' => $this->storeIdentifier]);

        try {
            // Retrieve store and decrypt token locally
            $store = VendorConnectedStore::where('store_identifier', $this->storeIdentifier)
                ->where('channel', 'shopify')
                ->firstOrFail();

            try {
                $credentials = decrypt($store->token);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                Log::error('Invalid store credentials: decryption failed.', [
                    'store_identifier' => $this->storeIdentifier,
                    'error' => $e->getMessage(),
                ]);
                $this->fail(new \Exception('Invalid store credentials: decryption failed.'));

                return;
            }

            $accessToken = $credentials['access_token'] ?? null;

            if (! $accessToken) {
                Log::error('Access token not found in store credentials for fulfillment registration', [
                    'store_identifier' => $this->storeIdentifier,
                ]);

                $this->fail(new \Exception('Access token not found.'));

                return;
            }

            $result = $service->register($this->storeIdentifier, $accessToken);

            if ($result && (! empty($result['service_id']) || ! empty($result['location_id']))) {
                // Update store with fulfillment service data in additional_data
                $additionalData = $store->additional_data ?? [];

                if (! empty($result['location_id'])) {
                    $additionalData['location_id'] = $result['location_id'];
                }

                if (! empty($result['service_id'])) {
                    $additionalData['fulfillment_service_id'] = $result['service_id'];
                }

                $store->additional_data = $additionalData;
                $store->save();

                Log::info('Updated store with Shopify fulfillment service data', [
                    'store_identifier' => $this->storeIdentifier,
                    'location_id' => $result['location_id'] ?? null,
                    'service_id' => $result['service_id'] ?? null,
                ]);
                Log::info('Successfully registered Shopify fulfillment service', ['store_identifier' => $this->storeIdentifier]);
            } else {
                Log::warning('Shopify fulfillment service registration returned incomplete result', [
                    'store_identifier' => $this->storeIdentifier,
                    'result' => $result,
                ]);
            }

        } catch (ModelNotFoundException $e) {
            Log::warning('Store not found for fulfillment registration, aborting', [
                'store_identifier' => $this->storeIdentifier,
            ]);
            $this->fail($e);
        } catch (\Throwable $e) {
            Log::error('Failed to register Shopify fulfillment service', [
                'store_identifier' => $this->storeIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
