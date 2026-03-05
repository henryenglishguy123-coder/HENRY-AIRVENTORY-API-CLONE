<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyProductJob implements ShouldQueue
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
        public string $topic,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing Shopify Product Webhook: {$this->topic}", [
            'shop' => $this->shopDomain,
            'product_id' => $this->payload['id'] ?? 'unknown',
        ]);

        $productId = $this->payload['id'] ?? null;
        if (! $productId) {
            Log::warning('Shopify Product Webhook: Missing product ID');

            return;
        }

        // Normalize domain to remove protocol and paths
        $host = parse_url($this->shopDomain, PHP_URL_HOST) ?? $this->shopDomain;
        $host = strtolower($host);

        // Find connected store - searching by exact match on store_identifier or link
        $store = VendorConnectedStore::where('channel', 'shopify')
            ->where(function ($query) use ($host) {
                $query->where('store_identifier', $host)
                    ->orWhere('link', $host)
                    ->orWhere('link', "https://{$host}");
            })
            ->first();

        if (! $store) {
            Log::warning('Shopify Product Webhook: Store not found', ['shop' => $this->shopDomain]);

            return;
        }

        // Find the local product linked to this Shopify product
        $storeOverride = VendorDesignTemplateStore::where('vendor_connected_store_id', $store->id)
            ->where('external_product_id', (string) $productId)
            ->first();

        if (! $storeOverride) {
            Log::info('Shopify Product Webhook: Product not linked locally', ['product_id' => $productId]);

            return;
        }

        // Handle specific topics
        if ($this->topic === 'products/delete') {
            $storeOverride->delete();
            Log::info('Shopify Product Webhook: Deleted store override entry', ['id' => $storeOverride->id]);
        } elseif ($this->topic === 'products/update') {
            // Update logic can be implemented here
            Log::info('Shopify Product Webhook: Product updated (no action taken yet)', ['id' => $storeOverride->id]);
        }
    }
}
