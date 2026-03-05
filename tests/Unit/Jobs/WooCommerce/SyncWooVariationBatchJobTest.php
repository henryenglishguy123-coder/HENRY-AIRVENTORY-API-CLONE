<?php

namespace Tests\Unit\Jobs\WooCommerce;

use App\Jobs\WooCommerce\SyncWooVariationBatchJob;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SyncWooVariationBatchJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_syncs_variation_batch()
    {
        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('name')->andReturn('Test Product');
        $storeOverride->vendor_connected_store_id = 99;

        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $connectedStore->storeChannel = $channel;

        $storeOverride->shouldReceive('load')->with(['connectedStore.storeChannel']);
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);
        $storeOverride->shouldReceive('relationLoaded')->with('connectedStore')->andReturn(true);

        $wooId = '1001';
        $batchCreate = [['sku' => 'v1']];
        $batchUpdate = [['sku' => 'v2']];

        // Mock Cache Lock (Success)
        $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('forceRelease')->once();
        Cache::shouldReceive('lock')->andReturn($lock);

        // Mock Connector
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncVariationBatch')
            ->once()
            ->with($wooId, $storeOverride, $batchCreate, $batchUpdate);

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->with($channel)->andReturn($connector);

        // Act
        $job = new SyncWooVariationBatchJob($storeOverride, $wooId, $batchCreate, $batchUpdate);
        $job->handle($factory);

        $this->assertTrue(true); // Assert no exception
    }

    public function test_handle_fails_on_exception()
    {
        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('name')->andReturn('Test Product');
        $storeOverride->vendor_connected_store_id = 99; // For RateLimiter and Metrics

        // Mock relation loading for RateLimiter and Connector
        $storeOverride->shouldReceive('relationLoaded')->with('connectedStore')->andReturn(false); // Job checks this
        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $connectedStore->storeChannel = $channel;

        $storeOverride->shouldReceive('load')->with(['connectedStore.storeChannel']);
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);

        $wooId = '1001';
        $batchCreate = [];
        $batchUpdate = [];

        // Mock Cache Lock (Success)
        $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('forceRelease')->once();
        Cache::shouldReceive('lock')->andReturn($lock);

        // Mock Connector to throw exception
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('syncVariationBatch')
            ->andThrow(new \Exception('Sync Failed'));

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->with($channel)->andReturn($connector);

        Log::shouldReceive('info')->withAnyArgs();
        Log::shouldReceive('error')->with('Sync job failed', Mockery::any())->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sync Failed');

        // Act
        $job = new SyncWooVariationBatchJob($storeOverride, $wooId, $batchCreate, $batchUpdate);
        $job->handle($factory);
    }

    public function test_job_releases_if_locked()
    {
        // Arrange
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->vendor_connected_store_id = 99;

        $wooId = '1001';
        $batchCreate = [['sku' => 'v1']];
        $batchUpdate = [];

        // Mock Cache Lock (Failure)
        $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->andReturn(false); // Lock not acquired
        // forceRelease should NOT be called if lock wasn't acquired (or handled differently, but in my code it returns early)
        Cache::shouldReceive('lock')->andReturn($lock);

        $factory = Mockery::mock(StoreConnectorFactory::class);
        // Factory should NOT be called
        $factory->shouldReceive('make')->never();

        // Act
        $job = new TestSyncWooVariationBatchJob($storeOverride, $wooId, $batchCreate, $batchUpdate);

        $job->handle($factory);

        $this->assertTrue($job->released);
    }
}

class TestSyncWooVariationBatchJob extends SyncWooVariationBatchJob
{
    public $released = false;

    public function release($delay = 0)
    {
        $this->released = true;
    }
}
