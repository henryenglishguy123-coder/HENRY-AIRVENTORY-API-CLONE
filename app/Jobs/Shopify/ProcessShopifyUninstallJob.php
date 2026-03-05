<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Enums\Store\StoreConnectionStatus;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyUninstallJob implements ShouldQueue
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
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $shopDomain,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing Shopify Uninstall', [
            'shop' => $this->shopDomain,
        ]);

        // Normalize domain to remove protocol and paths
        $host = parse_url($this->shopDomain, PHP_URL_HOST) ?? $this->shopDomain;
        $host = strtolower($host);

        // Disconnect store - searching by exact match on store_identifier or link
        $store = VendorConnectedStore::where('channel', 'shopify')
            ->where(function ($query) use ($host) {
                $query->where('store_identifier', $host)
                    ->orWhere('link', $host)
                    ->orWhere('link', "https://{$host}"); // Handle full URL if stored exactly
            })
            ->first();

        if ($store) {
            // Use the model helper or update status safely
            if (method_exists($store, 'markDisconnected')) {
                $store->markDisconnected('App uninstalled from Shopify');
                // Ensure token is cleared for security
                $store->update(['token' => null]);
            } else {
                $store->update([
                    'status' => StoreConnectionStatus::DISCONNECTED,
                    'error_message' => 'App uninstalled from Shopify',
                    // We typically keep the token or clear it depending on security policy
                    // but since they uninstalled, the token is invalid anyway.
                    'token' => null,
                ]);
            }

            Log::info('Shopify store disconnected successfully', [
                'shop' => $this->shopDomain,
                'store_id' => $store->id,
            ]);
        } else {
            Log::warning('Shopify store not found for uninstall', [
                'shop' => $this->shopDomain,
            ]);
        }
    }
}
