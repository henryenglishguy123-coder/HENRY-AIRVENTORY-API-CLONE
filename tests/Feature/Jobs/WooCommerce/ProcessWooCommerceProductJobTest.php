<?php

namespace Tests\Feature\Jobs\WooCommerce;

use App\Jobs\WooCommerce\ProcessWooCommerceProductJob;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class ProcessWooCommerceProductJobTest extends TestCase
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
            'email' => 'test@vendor.com',
            'password' => '',
            'account_status' => 'active',
            'source' => 'signup',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_handle_deletes_product_on_delete_topic()
    {
        // Setup Data
        $vendorId = 1;
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'woocommerce',
            'store_identifier' => 'test-store',
            'link' => 'https://test-store.com',
            'status' => 'connected',
            'token' => 'encrypted_token',
        ]);

        // Create Catalog Template
        $catalogTemplate = CatalogDesignTemplate::create([
            'name' => 'Test Catalog Template',
        ]);

        // Create Template
        $template = VendorDesignTemplate::create([
            'vendor_id' => $vendorId,
            'catalog_design_template_id' => $catalogTemplate->id,
            'name' => 'Test Template',
        ]);

        // Create Product
        $product = VendorDesignTemplateStore::create([
            'vendor_id' => $vendorId,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => '123456',
            'status' => 'active',
        ]);

        Log::shouldReceive('info')->with('Processing WooCommerce Product Webhook: product.deleted', \Mockery::any())->once();
        Log::shouldReceive('info')->with('WooCommerce Product Webhook: Deleted store override entry', ['id' => $product->id])->once();

        $job = new ProcessWooCommerceProductJob($store->id, 'product.deleted', ['id' => 123456]);
        $job->handle();

        $this->assertDatabaseMissing('vendor_design_template_stores', ['id' => $product->id]);
    }

    public function test_handle_logs_warning_if_product_id_missing()
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->with('WooCommerce Product Webhook: Missing product ID')->once();

        $job = new ProcessWooCommerceProductJob(1, 'product.deleted', []);
        $job->handle();
    }

    public function test_handle_logs_info_if_product_not_linked()
    {
        $vendorId = 1;
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendorId,
            'channel' => 'woocommerce',
            'store_identifier' => 'test-store',
            'link' => 'https://test-store.com',
            'status' => 'connected',
            'token' => 'encrypted_token',
        ]);

        Log::shouldReceive('info')->with('Processing WooCommerce Product Webhook: product.deleted', \Mockery::any())->once();
        Log::shouldReceive('info')->with('WooCommerce Product Webhook: Product not linked locally', [
            'store_id' => $store->id,
            'product_id' => '999999',
        ])->once();

        $job = new ProcessWooCommerceProductJob($store->id, 'product.deleted', ['id' => 999999]);
        $job->handle();
    }
}
