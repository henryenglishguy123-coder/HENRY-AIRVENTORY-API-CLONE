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

class DeleteWooCommerceWebhooksJob implements ShouldQueue
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
        Log::info('Starting WooCommerce webhook deletion', ['store_id' => $this->storeId]);

        try {
            $store = VendorConnectedStore::where('id', $this->storeId)
                ->where('channel', 'woocommerce')
                ->firstOrFail();

            $storeChannel = StoreChannel::where('code', 'woocommerce')->firstOrFail();

            /** @var \App\Services\Channels\WooCommerce\WooCommerceConnector $connector */
            $connector = $factory->make($storeChannel);

            $connector->deleteWebhooks($store);

            Log::info('Finished processing WooCommerce webhook deletion job', ['store_id' => $this->storeId]);
        } catch (ModelNotFoundException $e) {
            Log::warning('Store or WooCommerce store channel not found for webhook deletion, aborting', [
                'store_id' => $this->storeId,
                'missing_model' => method_exists($e, 'getModel') ? $e->getModel() : null,
            ]);
            // If store is gone, we can't delete webhooks anyway (need token), so just finish.
        } catch (\Throwable $e) {
            Log::error('Failed to delete WooCommerce webhooks', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
            ]);
            // We might want to retry if it's a network error, but for now let's throw to allow standard retry policy if configured
            throw $e;
        }
    }
}
