<?php

namespace Tests\Feature\Jobs\WooCommerce;

use App\Enums\Store\StoreConnectionStatus;
use App\Jobs\WooCommerce\ProcessWooCommerceOrderJob;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Location\Country;
use App\Services\Customer\Cart\CartPricingService;
use App\Services\Customer\Cart\CartRoutingService;
use App\Services\Sales\Order\CartToOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class ProcessWooCommerceOrderJobTest extends TestCase
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

    public function test_job_processes_woocommerce_order_payload_successfully()
    {
        // 1. Setup Data
        $vendorId = 1;
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'woocommerce',
            'store_identifier' => 'http://test-woo.com',
            'link' => 'http://test-woo.com',
            'token' => encrypt(['consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test']),
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
            'external_product_id' => '100',
            'status' => 'active',
        ]);

        $storeVariant = VendorDesignTemplateStoreVariant::create([
            'vendor_design_template_store_id' => $storeTemplate->id,
            'catalog_product_id' => $variantProduct->id,
            'sku' => 'generated-sku-uuid-0001',
            'markup' => 10,
            'markup_type' => 'fixed',
            'external_variant_id' => '101', // WooCommerce variation ID
            'is_enabled' => true,
        ]);

        // Payload
        $payload = [
            'id' => 12345,
            'number' => '1001',
            'date_created' => '2026-02-11T01:49:39', // WooCommerce often sends simpler ISO strings
            'status' => 'processing',
            'currency' => 'USD',
            'line_items' => [
                [
                    'id' => 99,
                    'name' => 'Custom T-Shirt',
                    'product_id' => 100,
                    'variation_id' => 101,
                    'quantity' => 2,
                    'tax_class' => '',
                    'subtotal' => '40.00',
                    'subtotal_tax' => '0.00',
                    'total' => '40.00',
                    'total_tax' => '0.00',
                    'sku' => 'TSHIRT-BLK-L', // WooCommerce might send the store SKU or nothing
                    'price' => 20.00,
                ],
            ],
            'shipping' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => '',
                'address_1' => '123 Main St',
                'address_2' => '',
                'city' => 'New York',
                'state' => 'NY',
                'postcode' => '10001',
                'country' => 'US',
                'phone' => '555-0123',
            ],
            'billing' => [
                'email' => 'customer@example.com',
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
            'woocommerce'
        )->andReturn(collect([new \stdClass]));

        $this->mock(\App\Services\Customer\Cart\CartTotalsService::class)
            ->shouldReceive('recalculate')
            ->once();

        // 3. Run Job
        $job = new ProcessWooCommerceOrderJob($store->id, 'order.created', $payload);
        $job->handle($mockRouting, $mockConversion, app(\App\Services\Channels\WooCommerce\OrderImportService::class));

        // 4. Assertions
        $this->assertDatabaseHas('carts', [
            'vendor_id' => $vendorId,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('cart_sources', [
            'platform' => 'woocommerce',
            'source_order_id' => (string) $payload['id'],
            'source_order_number' => (string) $payload['number'],
        ]);

        // Explicitly check the date conversion
        $source = \App\Models\Customer\Cart\CartSource::where('source_order_id', (string) $payload['id'])->first();
        $this->assertNotNull($source->source_created_at);
    }
}
