<?php

namespace Tests\Feature\Channels\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class WooCommerceSyncTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_sync_product_throws_exception_when_no_variants()
    {
        // Mock dependencies
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $dataService = Mockery::mock(WooCommerceDataService::class);

        $connector = new WooCommerceConnector($channel, $dataService);

        // Mock Store
        $store = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $store->token = encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $store->link = 'https://example.com';

        // Mock Store Override
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($store);
        $storeOverride->shouldReceive('getAttribute')->with('external_product_id')->andReturn(123);
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);

        // Mock variants relationship - returns empty collection
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([]));

        $storeOverride->shouldReceive('update')->andReturn(true);

        // Mock data service calls
        $dataService->shouldReceive('ensureProductRelationships')->once();
        $dataService->shouldReceive('ensureProductSku')->once();
        $dataService->shouldReceive('ensureVariantSkus')->once();
        $dataService->shouldReceive('prepareProductData')->andReturn([
            'id' => 123,
            'sku' => 'TEST-SKU',
            'name' => 'Test Product',
        ]);

        // Mock Http calls
        Http::fake([
            '*/products/123' => Http::response(['id' => 123], 200),
        ]);

        // Mock Log
        Log::shouldReceive('info');

        Log::shouldReceive('warning')
            ->with('WooCommerce Sync: Product has no variants', ['store_override_id' => 1])
            ->once();

        // Expect exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Product must have at least one variant to be synced.');

        // Act
        $connector->syncProduct($storeOverride);
    }

    public function test_sync_product_fails_on_timeout()
    {
        // Mock dependencies
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $connector = new WooCommerceConnector($channel, $dataService);

        // Mock Store
        $store = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $store->token = encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $store->link = 'https://example.com';
        $store->id = 99;

        // Mock Store Override
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($store);
        $storeOverride->shouldReceive('getAttribute')->with('external_product_id')->andReturn(null);
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('sku')->andReturn('TEST-SKU');
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([new \stdClass]));

        // Expect update to failed status
        $storeOverride->shouldReceive('update')->with(Mockery::on(function ($arg) {
            return isset($arg['sync_status']) && $arg['sync_status'] === 'failed';
        }))->once();

        // Mock data service calls
        $dataService->shouldReceive('ensureProductRelationships')->once();
        $dataService->shouldReceive('prepareProductData')->andReturn([
            'sku' => 'TEST-SKU',
            'name' => 'Test Product',
        ]);

        // Mock Http calls - Timeout immediately
        Http::fake([
            '*/products' => function ($request) {
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            },
        ]);

        Log::shouldReceive('info');
        Log::shouldReceive('error')->with('WooCommerce Connection Timeout', Mockery::any())->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('WooCommerce Connection Timeout');

        $connector->syncProduct($storeOverride);
    }

    public function test_sync_product_recovers_from_duplicate_sku()
    {
        // Mock dependencies
        $channel = new StoreChannel(['code' => 'woocommerce']);
        $dataService = Mockery::mock(WooCommerceDataService::class);

        $connector = new WooCommerceConnector($channel, $dataService);

        // Mock Store
        $store = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $store->token = encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $store->link = 'https://example.com';
        $store->id = 99;

        // Mock Store Override
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($store);
        $storeOverride->shouldReceive('getAttribute')->with('external_product_id')->andReturn(null); // Initially null
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('sku')->andReturn('TEST-SKU');

        // Mock variants relationship - returns 1 variant
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([new \stdClass]));

        // Expect update with recovered ID
        // update is called when recovered, and then at end of function
        $storeOverride->shouldReceive('update')->with(Mockery::on(function ($arg) {
            return isset($arg['external_product_id']) && $arg['external_product_id'] == 1550;
        }))->atLeast()->times(1);

        // Mock data service calls
        $dataService->shouldReceive('ensureProductRelationships')->atLeast()->times(1);
        $dataService->shouldReceive('prepareProductData')->andReturn([
            'sku' => 'TEST-SKU',
            'name' => 'Test Product',
        ]);

        // Mock getVariationBatches to return one empty batch (or no batches)
        $dataService->shouldReceive('getVariationBatches')
            ->andReturn((function () {
                yield ['create' => [], 'update' => []];
            })());

        $dataService->shouldReceive('batchUpdateVariantExternalIds');

        // Mock Http calls
        Http::fake([
            // Simulate Duplicate SKU directly (as if it's a retry from Job)
            '*/products' => Http::response([
                'code' => 'product_invalid_sku',
                'message' => 'Invalid or duplicated SKU.',
                'data' => [
                    'status' => 400,
                    'resource_id' => 1550,
                    'unique_sku' => 'TEST-SKU-1',
                ],
            ], 400),
            '*/products/1550/variations*' => Http::response([], 200),
            '*/products/1550/variations/batch' => Http::response(['create' => [], 'update' => []], 200),
        ]);

        // Mock Log
        Log::shouldReceive('info');
        Log::shouldReceive('warning');

        // Act
        $result = $connector->syncProduct($storeOverride);

        // Assert
        $this->assertEquals('1550', $result);
    }
}
