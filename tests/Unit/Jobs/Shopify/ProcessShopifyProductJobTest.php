<?php

namespace Tests\Unit\Jobs\Shopify;

use App\Jobs\Shopify\ProcessShopifyProductJob;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ProcessShopifyProductJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Workaround for missing vendors table migration in test environment
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 10, 4)->default(0);
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_templates')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_templates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('catalog_design_template_id')->nullable();
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_design_template')) {
            \Illuminate\Support\Facades\Schema::create('catalog_design_template', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_products')) {
            \Illuminate\Support\Facades\Schema::create('catalog_products', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_logs_warning_if_product_id_missing()
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->with('Shopify Product Webhook: Missing product ID')->once();

        $job = new ProcessShopifyProductJob('test.myshopify.com', 'products/update', []);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_if_store_not_found()
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->with('Shopify Product Webhook: Store not found', ['shop' => 'test.myshopify.com'])->once();

        $job = new ProcessShopifyProductJob('test.myshopify.com', 'products/update', ['id' => 123]);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_handle_deletes_product_on_delete_topic()
    {
        // Create Vendor
        $vendor = new \App\Models\Customer\Vendor;
        $vendor->name = 'Test Vendor';
        $vendor->email = 'test@vendor.com';
        $vendor->password = 'password';
        $vendor->save();

        $store = new \App\Models\Customer\Store\VendorConnectedStore;
        $store->vendor_id = $vendor->id;
        $store->channel = 'shopify';
        $store->store_identifier = 'test.myshopify.com';
        $store->status = \App\Enums\Store\StoreConnectionStatus::CONNECTED;
        $store->save();

        // Create Catalog Template
        $catalogTemplate = new \App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
        $catalogTemplate->name = 'Test Catalog Template';
        $catalogTemplate->save();

        // Create Template
        $template = new \App\Models\Customer\Designer\VendorDesignTemplate;
        $template->vendor_id = $vendor->id;
        $template->catalog_design_template_id = $catalogTemplate->id;
        $template->save();

        // Create Product
        $product = new VendorDesignTemplateStore;
        $product->vendor_id = $vendor->id;
        $product->vendor_connected_store_id = $store->id;
        $product->vendor_design_template_id = $template->id;
        $product->external_product_id = '123456';
        $product->save();

        Log::shouldReceive('info')->with('Processing Shopify Product Webhook: products/delete', Mockery::any())->once();
        Log::shouldReceive('info')->with('Shopify Product Webhook: Deleted store override entry', ['id' => $product->id])->once();

        $job = new ProcessShopifyProductJob('test.myshopify.com', 'products/delete', ['id' => 123456]);
        $job->handle();

        $this->assertDatabaseMissing('vendor_design_template_stores', ['id' => $product->id]);
    }

    public function test_handle_logs_info_on_update_topic()
    {
        // Create Vendor
        $vendor = new \App\Models\Customer\Vendor;
        $vendor->name = 'Test Vendor';
        $vendor->email = 'test@vendor.com';
        $vendor->password = 'password';
        $vendor->save();

        $store = new \App\Models\Customer\Store\VendorConnectedStore;
        $store->vendor_id = $vendor->id;
        $store->channel = 'shopify';
        $store->store_identifier = 'test.myshopify.com';
        $store->status = \App\Enums\Store\StoreConnectionStatus::CONNECTED;
        $store->save();

        // Create Catalog Template
        $catalogTemplate = new \App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
        $catalogTemplate->name = 'Test Catalog Template';
        $catalogTemplate->save();

        // Create Template
        $template = new \App\Models\Customer\Designer\VendorDesignTemplate;
        $template->vendor_id = $vendor->id;
        $template->catalog_design_template_id = $catalogTemplate->id;
        $template->save();

        $product = new VendorDesignTemplateStore;
        $product->vendor_id = $vendor->id;
        $product->vendor_connected_store_id = $store->id;
        $product->vendor_design_template_id = $template->id;
        $product->external_product_id = '123456';
        $product->save();

        Log::shouldReceive('info')->with('Processing Shopify Product Webhook: products/update', Mockery::any())->once();
        Log::shouldReceive('info')->with('Shopify Product Webhook: Product updated (no action taken yet)', ['id' => $product->id])->once();

        $job = new ProcessShopifyProductJob('test.myshopify.com', 'products/update', ['id' => 123456]);
        $job->handle();

        $this->assertDatabaseHas('vendor_design_template_stores', ['id' => $product->id]);
    }
}
