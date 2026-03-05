<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWooCommerceProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $storeId,
        public string $topic,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing WooCommerce Product Webhook: {$this->topic}", [
            'store_id' => $this->storeId,
            'product_id' => $this->payload['id'] ?? 'unknown',
        ]);

        $productId = $this->payload['id'] ?? null;
        if (! $productId) {
            Log::warning('WooCommerce Product Webhook: Missing product ID');

            return;
        }

        // Find the local product linked to this WooCommerce product
        $storeOverride = VendorDesignTemplateStore::where('vendor_connected_store_id', $this->storeId)
            ->where('external_product_id', (string) $productId)
            ->first();

        if (! $storeOverride) {
            Log::info('WooCommerce Product Webhook: Product not linked locally', [
                'store_id' => $this->storeId,
                'product_id' => $productId,
            ]);

            return;
        }

        // Handle specific topics
        if ($this->topic === 'product.deleted') {
            $storeOverride->delete();
            Log::info('WooCommerce Product Webhook: Deleted store override entry', ['id' => $storeOverride->id]);
        } elseif ($this->topic === 'product.updated') {
            Log::info('WooCommerce Product Webhook: Product updated (no action taken yet)', ['id' => $storeOverride->id]);
        }
    }
}
