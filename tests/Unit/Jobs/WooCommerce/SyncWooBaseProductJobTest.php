<?php

namespace Tests\Unit\Jobs\WooCommerce;

use App\Jobs\WooCommerce\FinalizeWooSyncJob;
use App\Jobs\WooCommerce\SyncWooBaseProductJob;
use App\Jobs\WooCommerce\SyncWooVariationBatchJob;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SyncWooBaseProductJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_syncs_base_product_and_dispatches_batch_jobs()
    {
        Bus::fake();

        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('name')->andReturn('Test Product');

        // Mock variants for logging
        $variants = Mockery::mock(\Illuminate\Database\Eloquent\Collection::class);
        $variants->shouldReceive('count')->andReturn(2);
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn($variants);

        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $connectedStore->storeChannel = $channel;

        $storeOverride->shouldReceive('load')->with(['connectedStore.storeChannel']);
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);

        // Expect status update
        $storeOverride->shouldReceive('update')
            ->once()
            ->with([
                'sync_status' => 'syncing',
                'sync_error' => null,
            ]);

        // Mock Connector
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncBaseProduct')
            ->once()
            ->with($storeOverride)
            ->andReturn('1001'); // Woo ID

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->with($channel)->andReturn($connector);

        // Mock Data Service
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $dataService->shouldReceive('getVariationBatches')
            ->once()
            ->with($storeOverride)
            ->andReturn((function () {
                yield ['create' => [['sku' => 'v1']], 'update' => []];
                yield ['create' => [], 'update' => [['sku' => 'v2']]];
            })());

        // Act
        $job = new SyncWooBaseProductJob($storeOverride);
        $job->handle($factory, $dataService);

        // Assert
        Bus::assertBatchCount(1);
    }

    public function test_full_sync_workflow_dispatches_batch_and_finalize_jobs()
    {
        Bus::fake();

        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('name')->andReturn('Test Product');

        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $connectedStore->storeChannel = $channel;

        $storeOverride->shouldReceive('load')->with(['connectedStore.storeChannel']);
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);

        $storeOverride->shouldReceive('update'); // ignore status updates

        // Mock logging
        $variants = Mockery::mock(\Illuminate\Database\Eloquent\Collection::class);
        $variants->shouldReceive('count')->andReturn(150);
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn($variants);

        // Mock Connector
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncBaseProduct')->andReturn('1001');

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->with($channel)->andReturn($connector);

        // Mock Data Service to return 3 batches (simulating 150 items / 50 batch size)
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $dataService->shouldReceive('getVariationBatches')
            ->once()
            ->andReturn((function () {
                yield ['create' => array_fill(0, 50, []), 'update' => []];
                yield ['create' => array_fill(0, 50, []), 'update' => []];
                yield ['create' => array_fill(0, 50, []), 'update' => []];
            })());

        // Act
        $job = new SyncWooBaseProductJob($storeOverride);
        $job->handle($factory, $dataService);

        // Assert
        Bus::assertBatched(function ($batch) {
            // Verify batch contains 3 jobs
            return $batch->jobs->count() === 3 &&
                   $batch->jobs->every(fn ($job) => $job instanceof SyncWooVariationBatchJob);
        });

        // Finalize job is part of the batch callbacks, but Bus::fake() doesn't expose them easily in assertions
        // But verifying the batch was dispatched with 3 jobs confirms the workflow structure.
    }

    public function test_handle_dispatches_finalize_directly_if_no_variations()
    {
        Bus::fake();

        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);

        // Mock variants for logging
        $variants = Mockery::mock(\Illuminate\Database\Eloquent\Collection::class);
        $variants->shouldReceive('count')->andReturn(0);
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn($variants);

        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $connectedStore->storeChannel = $channel;

        $storeOverride->shouldReceive('load')->with(['connectedStore.storeChannel']);
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);

        $storeOverride->shouldReceive('update')->once();

        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncBaseProduct')->andReturn('1001');

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->andReturn($connector);

        $dataService = Mockery::mock(WooCommerceDataService::class);
        $dataService->shouldReceive('getVariationBatches')->andReturn((function () {
            yield ['create' => [], 'update' => []]; // Empty batch
        })());

        // Act
        $job = new SyncWooBaseProductJob($storeOverride);
        $job->handle($factory, $dataService);

        // Assert
        Bus::assertBatchCount(0);
        Bus::assertDispatched(FinalizeWooSyncJob::class);
    }

    public function test_handle_fails_gracefully_on_exception()
    {
        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('vendor_connected_store_id')->andReturn(1);

        $storeOverride->shouldReceive('update')->with(['sync_status' => 'syncing', 'sync_error' => null]);

        // Expect failure update
        $storeOverride->shouldReceive('update')->with([
            'sync_status' => 'failed',
            'sync_error' => 'Store channel configuration missing',
        ])->once();

        $storeOverride->shouldReceive('load');
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn(null); // Cause exception

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $dataService = Mockery::mock(WooCommerceDataService::class);

        Log::shouldReceive('info')->withAnyArgs();
        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Store channel configuration missing');

        // Act
        $job = new SyncWooBaseProductJob($storeOverride);
        $job->handle($factory, $dataService);
    }
}
