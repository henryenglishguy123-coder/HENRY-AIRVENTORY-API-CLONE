<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Enums\Store\StoreConnectionStatus;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckWooCommerceConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $storeId
    ) {
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(StoreConnectorFactory $factory): void
    {
        try {
            $store = VendorConnectedStore::where('id', $this->storeId)
                ->where('channel', 'woocommerce')
                ->first();

            if (! $store) {
                // Store might have been deleted already
                return;
            }

            // Skip if already disconnected (unless we want to re-check?)
            // Usually we only check connected stores
            if ($store->status !== StoreConnectionStatus::CONNECTED) {
                return;
            }

            $storeChannel = StoreChannel::where('code', 'woocommerce')->firstOrFail();

            /** @var \App\Services\Channels\WooCommerce\WooCommerceConnector $connector */
            $connector = $factory->make($storeChannel);

            // Decrypt credentials
            try {
                $decryptedToken = decrypt($store->token);
            } catch (\Exception $e) {
                // Token invalid/corrupted
                $store->markError('Invalid credentials token');

                return;
            }

            $credentials = [
                'link' => $store->link,
            ];

            if (is_array($decryptedToken)) {
                $credentials['consumer_key'] = $decryptedToken['consumer_key'] ?? null;
                $credentials['consumer_secret'] = $decryptedToken['consumer_secret'] ?? null;
            } else {
                // Should not happen for Woo, but safe fallback
                $credentials['consumer_key'] = null;
                $credentials['consumer_secret'] = null;
            }

            if (! $connector->verify($credentials)) {
                Log::warning('WooCommerce Connection Check Failed - Marking as Disconnected', [
                    'store_id' => $store->id,
                ]);
                $store->markDisconnected('Connection lost: Unable to verify credentials (keys may have been revoked)');

                // We do NOT dispatch DeleteWebhooks here because if keys are revoked, we can't delete them.
                // But we could try just in case.
            } else {
                // Connection is good.
                // Optionally update a 'last_checked_at' timestamp if we had one.
                Log::info('WooCommerce Connection Check Passed', ['store_id' => $store->id]);
            }

        } catch (\Throwable $e) {
            Log::error('Failed to check WooCommerce connection', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the job for connection errors, just log
        }
    }
}
