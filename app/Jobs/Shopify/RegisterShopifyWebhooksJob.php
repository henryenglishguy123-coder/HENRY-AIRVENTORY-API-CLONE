<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Services\Channels\Shopify\ShopifyWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterShopifyWebhooksJob implements ShouldQueue
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
    public function handle(ShopifyWebhookService $webhookService): void
    {
        Log::info('Starting Shopify webhook registration', ['store_identifier' => $this->storeIdentifier]);

        try {
            // Retrieve store and decrypt token locally
            $store = \App\Models\Customer\Store\VendorConnectedStore::where('store_identifier', $this->storeIdentifier)
                ->where('channel', 'shopify') // Ensure we get the correct channel
                ->firstOrFail();

            $credentials = decrypt($store->token);
            $accessToken = $credentials['access_token'] ?? null;

            if (! $accessToken) {
                // If token is missing, this is a data integrity issue, not transient. Fail job.
                Log::error('Access token not found in store credentials for webhook registration', [
                    'store_identifier' => $this->storeIdentifier,
                ]);

                $this->fail(new \Exception('Access token not found in store credentials.'));

                return;
            }

            $webhookService->register($this->storeIdentifier, $accessToken);
            Log::info('Successfully registered Shopify webhooks', ['store_identifier' => $this->storeIdentifier]);
        } catch (ModelNotFoundException $e) {
            // Store was deleted or not found - permanent failure, do not retry
            Log::warning('Store not found for webhook registration, aborting', [
                'store_identifier' => $this->storeIdentifier,
            ]);

            $this->fail($e);
        } catch (\Throwable $e) {
            Log::error('Failed to register Shopify webhooks', [
                'store_identifier' => $this->storeIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Trigger retry for other errors (e.g., network)
        }
    }
}
