<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterWooCommerceWebhooksJob implements ShouldQueue
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
        public int $storeId
    ) {
        $this->onQueue('integrations');
    }

    /**
     * Execute the job.
     */
    public function handle(StoreConnectorFactory $factory): void
    {
        Log::info('Starting WooCommerce webhook registration', ['store_id' => $this->storeId]);

        try {
            $store = VendorConnectedStore::where('id', $this->storeId)
                ->where('channel', 'woocommerce')
                ->firstOrFail();

            $storeChannel = StoreChannel::where('code', 'woocommerce')->firstOrFail();

            /** @var \App\Services\Channels\WooCommerce\WooCommerceConnector $connector */
            $connector = $factory->make($storeChannel);

            $connector->registerWebhooks($store);

            Log::info('Successfully registered WooCommerce webhooks', ['store_id' => $this->storeId]);
        } catch (ModelNotFoundException $e) {
            Log::warning('Store not found for webhook registration, aborting', [
                'store_id' => $this->storeId,
            ]);
            $this->fail($e);
        } catch (\Throwable $e) {
            Log::error('Failed to register WooCommerce webhooks', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
