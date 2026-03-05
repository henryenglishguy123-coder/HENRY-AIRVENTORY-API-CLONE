<?php

namespace Tests\Feature\Jobs\Shopify;

use App\Enums\Store\StoreConnectionStatus;
use App\Jobs\Shopify\ProcessShopifyOrderJob;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
// use App\Models\Customer\Vendor;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Location\Country;
use App\Services\Customer\Cart\CartPricingService;
use App\Services\Customer\Cart\CartRoutingService;
use App\Services\Sales\Order\CartToOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class ProcessShopifyOrderJobTest extends TestCase
{
    use CreatesTestTables, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        \DB::table('vendors')->insert([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'Vendor',
            'email' => 'vendor@example.com',
            'password' => '',
            'account_status' => 'active',
            'source' => 'signup',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Country::create([
            'id' => 1,
            'name' => 'United States',
            'iso2' => 'US',
        ]);

        \DB::table('factory_shipping_rates')->insert([
            'factory_id' => 1,
            'country_code' => 'US',
            'rate' => 5.00,
        ]);
    }

    public function test_job_processes_shopify_order_payload_successfully()
    {
        // 1. Setup Data
        $vendorId = 1;
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'link' => 'https://test-shop.myshopify.com',
            'token' => 'token',
            'currency' => 'USD',
            'status' => StoreConnectionStatus::CONNECTED,
        ]);

        // Catalog Product & Variant
        $parentProduct = CatalogProduct::factory()->create(['name' => 'T-Shirt']);
        $variantProduct = CatalogProduct::factory()->create(['sku' => 'TSHIRT-BLK-L', 'type' => 'variant']);

        \DB::table('catalog_product_parents')->insert([
            'parent_id' => $parentProduct->id,
            'catalog_product_id' => $variantProduct->id,
        ]);

        // Design Template & Store Variant
        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendorId]);
        $storeTemplate = VendorDesignTemplateStore::create([
            'vendor_id' => $vendorId,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => 'ext_prod_1',
            'status' => 'active',
        ]);

        $storeVariant = VendorDesignTemplateStoreVariant::create([
            'vendor_design_template_store_id' => $storeTemplate->id,
            'catalog_product_id' => $variantProduct->id,
            'sku' => 'generated-sku-uuid-0001',
            'markup' => 10,
            'markup_type' => 'fixed',
            'external_variant_id' => 'gid://shopify/ProductVariant/987654321',
            'is_enabled' => true,
        ]);

        // Payload
        $shopifySku = '20260210-uuid-'.$storeVariant->id;

        $payload = [
            'id' => 123456789,
            'created_at' => '2026-02-11T01:49:39-05:00',
            'order_number' => 1001,
            'email' => 'customer@example.com',
            'currency' => 'USD',
            'line_items' => [
                [
                    'sku' => $shopifySku,
                    'variant_id' => 'gid://shopify/ProductVariant/987654321',
                    'quantity' => 2,
                    'name' => 'Custom T-Shirt',
                    'price' => 20.00,
                ],
            ],
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address1' => '123 Main St',
                'city' => 'New York',
                'province' => 'New York',
                'province_code' => 'NY',
                'country' => 'United States',
                'country_code' => 'US',
                'zip' => '10001',
                'phone' => '555-0123',
            ],
        ];

        // 2. Mock Services
        $mockRouting = $this->mock(CartRoutingService::class);
        $mockRouting->shouldReceive('processCartRouting')->once()->with(Mockery::on(function ($cart) use ($vendorId) {
            return $cart->vendor_id === $vendorId;
        }));

        // Mock pricing service used inside the job
        $mockPricing = $this->mock(CartPricingService::class);
        $mockPricing->shouldReceive('resolveUnitPrice')->andReturn(20.00);
        $mockPricing->shouldReceive('getFulfillmentFactoryId')->andReturn(1);

        $mockConversion = $this->mock(CartToOrderService::class);
        $mockConversion->shouldReceive('convert')->once()->with(
            Mockery::on(function ($cart) use ($variantProduct, $parentProduct) {
                $item = $cart->items->first();
                if ($cart->items->count() !== 1) {
                    return false;
                }
                if ((int) $item->variant_id !== (int) $variantProduct->id) {
                    return false;
                }
                if ((int) $item->product_id !== (int) $parentProduct->id) {
                    return false;
                }
                if ((float) $item->unit_price !== 20.00) {
                    return false;
                }
                if ((float) $item->line_total !== 40.00) {
                    return false;
                }
                if ((int) $item->fulfillment_factory_id !== 1) {
                    return false;
                }
                if ($cart->address->first_name !== 'John') {
                    return false;
                }

                return true;
            }),
            'shopify'
        )->andReturn(collect([new \stdClass]));

        $this->mock(\App\Services\Customer\Cart\CartTotalsService::class)
            ->shouldReceive('recalculate')
            ->once();

        // 3. Run Job
        $job = new ProcessShopifyOrderJob('test-shop.myshopify.com', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));

        // 4. Assertions
        $this->assertDatabaseHas('carts', [
            'vendor_id' => $vendorId,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('cart_sources', [
            'platform' => 'shopify',
            'source_order_id' => (string) $payload['id'],
            'source_order_number' => (string) $payload['order_number'],
            // 'source_created_at' => '2026-02-11 06:49:39', // 01:49 -05:00 is 06:49 UTC
        ]);

        // Explicitly check the date conversion
        $source = \App\Models\Customer\Cart\CartSource::where('source_order_id', (string) $payload['id'])->first();
        // 2026-02-11T01:49:39-05:00 is 2026-02-11 06:49:39 UTC
        $this->assertEquals('2026-02-11 06:49:39', $source->source_created_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_invalid_store_identifier_should_fail()
    {
        $vendorId = 1;
        VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'shopify',
            'store_identifier' => 'another-shop.myshopify.com',
            'status' => StoreConnectionStatus::CONNECTED,
        ]);
        $payload = ['id' => 111, 'order_number' => 2002, 'line_items' => []];
        $mockRouting = $this->mock(CartRoutingService::class);
        $mockConversion = $this->mock(CartToOrderService::class);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $job = new ProcessShopifyOrderJob('missing-shop.myshopify.com', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_sources', 0);
    }

    public function test_missing_or_invalid_sku_should_skip_line_item_or_fail()
    {
        $vendorId = 1;
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'status' => StoreConnectionStatus::CONNECTED,
        ]);
        $payload = [
            'id' => 222,
            'order_number' => 3003,
            'email' => 'customer@example.com',
            'currency' => 'USD',
            'line_items' => [
                [
                    'sku' => null,
                    'variant_id' => 'invalid',
                    'quantity' => 1,
                    'name' => 'Bad Item',
                ],
            ],
        ];
        $mockRouting = $this->mock(CartRoutingService::class);
        $mockRouting->shouldReceive('processCartRouting')->once();
        $mockPricing = $this->mock(CartPricingService::class);
        $mockConversion = $this->mock(CartToOrderService::class);
        $mockConversion->shouldReceive('convert')->never();
        $job = new ProcessShopifyOrderJob('test-shop.myshopify.com', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));
        $this->assertDatabaseHas('cart_sources', [
            'platform' => 'shopify',
            'source_order_id' => (string) $payload['id'],
        ]);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('cart_errors', [
            'error_code' => 'VARIANT_NOT_FOUND',
        ]);
    }

    public function test_missing_shipping_address_should_handle_gracefully()
    {
        $vendorId = 1;
        VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'status' => StoreConnectionStatus::CONNECTED,
        ]);
        $parentProduct = CatalogProduct::factory()->create(['name' => 'T-Shirt']);
        $variantProduct = CatalogProduct::factory()->create(['sku' => 'TSHIRT-BLK-L', 'type' => 'variant']);
        \DB::table('catalog_product_parents')->insert([
            'parent_id' => $parentProduct->id,
            'catalog_product_id' => $variantProduct->id,
        ]);
        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendorId]);
        $storeTemplate = VendorDesignTemplateStore::create([
            'vendor_id' => $vendorId,
            'vendor_connected_store_id' => VendorConnectedStore::first()->id,
            'vendor_design_template_id' => $template->id,
            'status' => 'active',
        ]);
        $storeVariant = VendorDesignTemplateStoreVariant::create([
            'vendor_design_template_store_id' => $storeTemplate->id,
            'catalog_product_id' => $variantProduct->id,
            'sku' => 'generated-sku-uuid-0002',
            'markup' => 10,
            'markup_type' => 'fixed',
            'external_variant_id' => 'gid://shopify/ProductVariant/123',
            'is_enabled' => true,
        ]);
        $payload = [
            'id' => 333,
            'order_number' => 4004,
            'email' => 'customer@example.com',
            'currency' => 'USD',
            'line_items' => [
                [
                    'sku' => '20260210-uuid-'.$storeVariant->id,
                    'variant_id' => 'gid://shopify/ProductVariant/123',
                    'quantity' => 1,
                    'name' => 'Custom T-Shirt',
                ],
            ],
        ];
        $mockRouting = $this->mock(CartRoutingService::class);
        $mockRouting->shouldReceive('processCartRouting')->once();
        $mockPricing = $this->mock(CartPricingService::class);
        $mockPricing->shouldReceive('resolveUnitPrice')->andReturn(10.00);
        $mockPricing->shouldReceive('getFulfillmentFactoryId')->andReturn(1);
        $mockConversion = $this->mock(CartToOrderService::class);
        $mockConversion->shouldReceive('convert')->never();
        $job = new ProcessShopifyOrderJob('test-shop.myshopify.com', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));
        $this->assertDatabaseCount('cart_addresses', 0);
        $this->assertDatabaseHas('cart_errors', ['error_code' => 'MISSING_ADDRESS']);
    }

    public function test_missing_email_or_phone_should_hold_cart_and_abort_conversion()
    {
        $vendorId = 1;
        VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'status' => StoreConnectionStatus::CONNECTED,
        ]);
        $parentProduct = CatalogProduct::factory()->create(['name' => 'T-Shirt']);
        $variantProduct = CatalogProduct::factory()->create(['sku' => 'TSHIRT-BLK-L', 'type' => 'variant']);
        \DB::table('catalog_product_parents')->insert([
            'parent_id' => $parentProduct->id,
            'catalog_product_id' => $variantProduct->id,
        ]);
        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendorId]);
        $storeTemplate = VendorDesignTemplateStore::create([
            'vendor_id' => $vendorId,
            'vendor_connected_store_id' => VendorConnectedStore::first()->id,
            'vendor_design_template_id' => $template->id,
            'status' => 'active',
        ]);
        $storeVariant = VendorDesignTemplateStoreVariant::create([
            'vendor_design_template_store_id' => $storeTemplate->id,
            'catalog_product_id' => $variantProduct->id,
            'sku' => 'generated-sku-uuid-0003',
            'markup' => 10,
            'markup_type' => 'fixed',
            'external_variant_id' => 'gid://shopify/ProductVariant/124',
            'is_enabled' => true,
        ]);
        $payload = [
            'id' => 666,
            'order_number' => 7007,
            'email' => '', // missing email
            'currency' => 'USD',
            'line_items' => [
                [
                    'sku' => '20260210-uuid-'.$storeVariant->id,
                    'variant_id' => 'gid://shopify/ProductVariant/124',
                    'quantity' => 1,
                    'name' => 'Custom T-Shirt',
                ],
            ],
            'shipping_address' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address1' => '456 Main St',
                'city' => 'Los Angeles',
                'province' => 'California',
                'province_code' => 'CA',
                'country' => 'United States',
                'country_code' => 'US',
                'zip' => '90001',
                'phone' => '', // missing phone
            ],
        ];
        $mockRouting = $this->mock(CartRoutingService::class);
        $mockRouting->shouldReceive('processCartRouting')->once();
        $mockPricing = $this->mock(CartPricingService::class);
        $mockPricing->shouldReceive('resolveUnitPrice')->andReturn(10.00);
        $mockPricing->shouldReceive('getFulfillmentFactoryId')->andReturn(1);
        $mockConversion = $this->mock(CartToOrderService::class);
        $mockConversion->shouldReceive('convert')->never();
        $job = new ProcessShopifyOrderJob('test-shop.myshopify.com', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));
        $this->assertDatabaseHas('cart_errors', [
            'error_code' => 'CONTACT_REQUIRED',
        ]);
    }

    public function test_duplicate_order_is_idempotent()
    {
        $vendorId = 1;
        VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'status' => StoreConnectionStatus::CONNECTED,
        ]);
        $payload = [
            'id' => 444,
            'order_number' => 5005,
            'line_items' => [],
        ];
        $mockRouting = $this->mock(CartRoutingService::class);
        $mockRouting->shouldReceive('processCartRouting')->once();
        $mockConversion = $this->mock(CartToOrderService::class);
        $job = new ProcessShopifyOrderJob('test-shop.myshopify.com', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\Shopify\OrderImportService::class));
        $this->assertEquals(1, \DB::table('cart_sources')->where('platform', 'shopify')->where('source_order_id', '444')->count());
    }
}
