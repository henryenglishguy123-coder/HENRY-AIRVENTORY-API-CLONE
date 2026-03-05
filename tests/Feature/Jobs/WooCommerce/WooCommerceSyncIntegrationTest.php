<?php

namespace Tests\Feature\Jobs\WooCommerce;

use App\Jobs\WooCommerce\SyncWooBaseProductJob;
use App\Jobs\WooCommerce\SyncWooVariationBatchJob;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class WooCommerceSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Manually create vendors table if it doesn't exist (fixing missing migration issue in test env)
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('mobile')->nullable();
                $table->string('password');
                $table->timestamp('last_login')->nullable();
                $table->string('source')->default('signup');
                $table->integer('account_status')->default(1);
                $table->string('social_login_id')->nullable();
                $table->string('gateway_customer_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Manually create vendor_wallets table if it doesn't exist
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 16, 4)->default(0);
                $table->timestamps();
            });
        }

        // Manually create vendor_connected_stores table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_connected_stores')) {
            \Illuminate\Support\Facades\Schema::create('vendor_connected_stores', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->string('channel');
                $table->string('link')->nullable();
                $table->text('token')->nullable();
                $table->string('store_identifier')->nullable();
                $table->json('additional_data')->nullable();
                $table->string('status')->default('connected');
                $table->timestamp('last_synced_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        // Manually create vendor_design_templates table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_templates')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_templates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('catalog_design_template_id')->nullable();
                $table->timestamps();
            });
        }

        // Manually create catalog_design_template table
        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_design_template')) {
            \Illuminate\Support\Facades\Schema::create('catalog_design_template', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Manually create store_channels table
        if (! \Illuminate\Support\Facades\Schema::hasTable('store_channels')) {
            \Illuminate\Support\Facades\Schema::create('store_channels', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('code');
                $table->string('name');
                $table->string('logo')->nullable();
                $table->text('description')->nullable();
                $table->string('auth_type')->default('api_key');
                $table->json('required_credentials')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Manually create vendor_design_template_stores table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_template_stores')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_template_stores', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('vendor_design_template_id');
                $table->unsignedBigInteger('vendor_connected_store_id');
                $table->string('name')->nullable();
                $table->string('sku')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->nullable();
                $table->string('sync_status')->default('pending');
                $table->string('external_product_id')->nullable();
                $table->text('sync_error')->nullable();
                $table->timestamps();
            });
        }

        // Run migrations to ensure other tables exist
        $this->artisan('migrate');
        // Mock RateLimiter to avoid throttle in tests
        $this->app['cache']->driver()->put('woo-sync:1', 0, 60);
    }

    public function test_full_sync_workflow_success()
    {
        // 1. Setup Data
        // StoreChannel doesn't have a factory, so we create it directly
        $channel = StoreChannel::where('code', 'woocommerce')->first();
        if (! $channel) {
            $channel = new StoreChannel;
            $channel->code = 'woocommerce';
            $channel->name = 'WooCommerce';
            $channel->auth_type = 'api_key';
            $channel->is_active = true;
            $channel->save();
        }

        $store = new VendorConnectedStore;
        $store->id = 1;
        // Need a vendor
        $vendor = \App\Models\Customer\Vendor::factory()->create();
        $store->vendor_id = $vendor->id;
        $store->channel = 'woocommerce';
        $store->store_identifier = 'test-store';
        $store->link = 'https://test-store.com';
        $store->token = encrypt(['consumer_key' => 'ck_123', 'consumer_secret' => 'cs_123']);
        $store->status = 'connected';
        $store->save();

        $template = new \App\Models\Customer\Designer\VendorDesignTemplate;
        $template->vendor_id = $vendor->id;

        // Create catalog template to satisfy FK
        $catalogTemplate = new \App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
        $catalogTemplate->name = 'Test Template';
        $catalogTemplate->status = true;
        $catalogTemplate->save();

        $template->catalog_design_template_id = $catalogTemplate->id;
        $template->save();

        $storeOverride = new VendorDesignTemplateStore;
        $storeOverride->vendor_id = $vendor->id;
        $storeOverride->vendor_design_template_id = $template->id;
        $storeOverride->vendor_connected_store_id = $store->id;
        $storeOverride->sync_status = 'pending';
        $storeOverride->save();

        // Mock Connector
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncBaseProduct')->andReturn('1001');

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->andReturn($connector);
        $this->app->instance(StoreConnectorFactory::class, $factory);

        // Mock Data Service to return 2 batches
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $dataService->shouldReceive('getVariationBatches')
            ->andReturn((function () {
                yield ['create' => array_fill(0, 2, []), 'update' => []]; // Batch 1
                yield ['create' => array_fill(0, 2, []), 'update' => []]; // Batch 2
            })());
        $this->app->instance(WooCommerceDataService::class, $dataService);

        // 2. Dispatch Base Job
        Bus::fake();

        $job = new SyncWooBaseProductJob($storeOverride);
        $job->handle($factory, $dataService);

        // 3. Verify Batch Dispatch
        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2 &&
                   $batch->jobs->first() instanceof SyncWooVariationBatchJob;
        });

        // 4. Verify Finalize Job is in the chain (Bus::fake doesn't execute the batch, so we verify structure)
        // Since we can't easily inspect the 'then' callback of a faked batch, we rely on the unit test for that.
        // But we can verify the base job finished without error.

        $this->assertEquals('syncing', $storeOverride->fresh()->sync_status);
    }
}
