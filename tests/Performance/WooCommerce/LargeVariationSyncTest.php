<?php

namespace Tests\Performance\WooCommerce;

use App\Jobs\WooCommerce\SyncWooBaseProductJob;
use App\Jobs\WooCommerce\SyncWooVariationBatchJob;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplate;
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

class LargeVariationSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Manually create necessary tables if they don't exist (copying from Integration Test setup)
        $this->createMissingTables();

        $this->artisan('migrate');
        $this->app['cache']->driver()->put('woo-sync:1', 0, 60); // Reset rate limiter
    }

    protected function createMissingTables()
    {
        // Copy schema creation logic from Integration Test to ensure standalone execution
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function ($table) {
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
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 16, 4)->default(0);
                $table->timestamps();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_connected_stores')) {
            \Illuminate\Support\Facades\Schema::create('vendor_connected_stores', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->string('channel');
                $table->string('store_identifier')->nullable();
                $table->text('token')->nullable();
                $table->string('status')->default('connected');
                $table->timestamps();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_templates')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_templates', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('catalog_design_template_id')->nullable();
                $table->timestamps();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_design_template')) {
            \Illuminate\Support\Facades\Schema::create('catalog_design_template', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('store_channels')) {
            \Illuminate\Support\Facades\Schema::create('store_channels', function ($table) {
                $table->id();
                $table->string('code');
                $table->string('name');
                $table->string('auth_type')->default('api_key');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_template_stores')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_template_stores', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('vendor_design_template_id');
                $table->unsignedBigInteger('vendor_connected_store_id');
                $table->string('name')->nullable();
                $table->string('sync_status')->default('pending');
                $table->text('sync_error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_large_sync_dispatch_performance_1000_items()
    {
        // 1. Setup Data
        $channel = StoreChannel::where('code', 'woocommerce')->first();
        if (! $channel) {
            $channel = new StoreChannel([
                'code' => 'woocommerce',
                'name' => 'WooCommerce',
                'is_active' => true,
                'auth_type' => 'api_key',
            ]);
            $channel->save();
        }

        $vendor = \App\Models\Customer\Vendor::factory()->create();

        $store = new VendorConnectedStore;
        $store->vendor_id = $vendor->id;
        $store->channel = 'woocommerce';
        $store->store_identifier = 'perf-store';
        $store->status = 'connected';
        $store->save();

        $catalogTemplate = new CatalogDesignTemplate(['name' => 'Perf Template', 'status' => true]);
        $catalogTemplate->save();

        $template = new VendorDesignTemplate(['vendor_id' => $vendor->id, 'catalog_design_template_id' => $catalogTemplate->id]);
        $template->save();

        $storeOverride = new VendorDesignTemplateStore;
        $storeOverride->vendor_id = $vendor->id;
        $storeOverride->vendor_design_template_id = $template->id;
        $storeOverride->vendor_connected_store_id = $store->id;
        $storeOverride->sync_status = 'pending';
        $storeOverride->save();

        // 2. Mock Dependencies
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncBaseProduct')->andReturn('1001');

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->andReturn($connector);

        // Mock Data Service to yield 20 batches of 50 items (1000 total)
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $dataService->shouldReceive('getVariationBatches')
            ->andReturn((function () {
                for ($i = 0; $i < 20; $i++) {
                    yield [
                        'create' => array_fill(0, 50, ['sku' => "sku-{$i}"]),
                        'update' => [],
                    ];
                }
            })());

        // 3. Measure Execution Time
        Bus::fake();

        $start = microtime(true);

        $job = new SyncWooBaseProductJob($storeOverride);
        $job->handle($factory, $dataService);

        $end = microtime(true);
        $duration = ($end - $start) * 1000;

        // 4. Assertions
        // Ensure dispatching 1000 items (20 batches) is fast (under 1 second for just dispatching logic)
        // Note: This tests the efficiency of the loop and job creation, not the actual HTTP requests (since mocked)
        $this->assertLessThan(1000, $duration, "Dispatching 1000 variations took too long: {$duration}ms");

        Bus::assertBatched(function ($batch) {
            // Check we have 20 jobs in the batch
            return $batch->jobs->count() === 20 &&
                   $batch->jobs->first() instanceof SyncWooVariationBatchJob;
        });

        // Check logs/memory if possible, but basic assertion is good.
        $this->assertEquals('syncing', $storeOverride->fresh()->sync_status);
    }
}
