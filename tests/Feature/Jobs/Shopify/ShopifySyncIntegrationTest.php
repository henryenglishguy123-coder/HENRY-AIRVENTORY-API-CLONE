<?php

namespace Tests\Feature\Jobs\Shopify;

use App\Jobs\Shopify\FinalizeShopifySyncJob;
use App\Jobs\Shopify\SyncShopifyBaseProductJob;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Shopify\ShopifyDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifySyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure tables exist or are migrated (RefreshDatabase handles this usually)
        // Create Shopify Channel
        if (! StoreChannel::where('code', 'shopify')->exists()) {
            StoreChannel::create([
                'code' => 'shopify',
                'name' => 'Shopify',
                'auth_type' => 'oauth',
                'is_active' => true,
            ]);
        }
    }

    public function test_shopify_full_sync_flow()
    {
        Bus::fake();

        // 1. Setup Data
        $vendor = \App\Models\Customer\Vendor::factory()->create();

        $store = new VendorConnectedStore;
        $store->vendor_id = $vendor->id;
        $store->channel = 'shopify';
        $store->store_identifier = 'test-shop.myshopify.com';
        $store->link = 'https://test-shop.myshopify.com';
        $store->token = encrypt(['access_token' => 'shpat_test', 'shop' => 'test-shop.myshopify.com']);
        $store->status = 'connected';
        $store->save();

        $catalogTemplate = new CatalogDesignTemplate;
        $catalogTemplate->name = 'Test Template';
        $catalogTemplate->status = true;
        $catalogTemplate->save();

        $template = new \App\Models\Customer\Designer\VendorDesignTemplate;
        $template->vendor_id = $vendor->id;
        $template->catalog_design_template_id = $catalogTemplate->id;
        $template->save();

        $storeOverride = new VendorDesignTemplateStore;
        $storeOverride->vendor_id = $vendor->id;
        $storeOverride->vendor_design_template_id = $template->id;
        $storeOverride->vendor_connected_store_id = $store->id;
        $storeOverride->sync_status = 'pending';
        $storeOverride->name = 'Test Product';
        $storeOverride->save();

        // Mock ShopifyDataService to return empty batches for simplicity in this test
        // or let it run with empty variants (it should create product and finalize)

        // Mock Http for Base Product Creation
        Http::fake([
            '*/products.json' => Http::response([
                'product' => [
                    'id' => 123456789,
                    'title' => 'Test Product',
                ],
            ], 201),
        ]);

        // 2. Dispatch Job
        $job = new SyncShopifyBaseProductJob($storeOverride);
        $job->handle(app(\App\Services\Channels\Factory\StoreConnectorFactory::class), app(ShopifyDataService::class));

        // 3. Assertions
        $storeOverride->refresh();
        $this->assertEquals('123456789', $storeOverride->external_product_id);

        // Since no variants, it should have dispatched FinalizeShopifySyncJob immediately
        Bus::assertDispatched(FinalizeShopifySyncJob::class);
    }
}
