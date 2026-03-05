<?php

namespace Tests\Unit\Services\Channels\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class WooCommerceConnectorSSRFTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_verify_blocks_private_ip()
    {
        $channel = new StoreChannel;
        $connector = new WooCommerceConnector($channel, Mockery::mock(WooCommerceDataService::class));

        $credentials = [
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
            'link' => 'https://192.168.1.1',
        ];

        // Ensure no actual request is made to this IP in production/real environment if possible,
        // but Http::fake prevents it.
        // We want to verify that validation fails BEFORE Http call.

        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $result = $connector->verify($credentials);

        $this->assertFalse($result, 'Verify should return false for private IP');

        // Assert that no request was sent if we want to be strict, but verify() catches exceptions.
        // If validation throws exception, verify() catches it and returns false.
        // If validation passes (and request is made), Http::fake returns 200, so verify() returns true.
        // So assertFalse($result) is sufficient to prove validation worked (failed).
    }

    public function test_sync_product_throws_exception_for_private_ip()
    {
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $channel = new StoreChannel;
        $connector = new WooCommerceConnector($channel, $dataService);

        $store = new VendorConnectedStore;
        $store->token = encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $store->link = 'https://127.0.0.1';

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->connectedStore = $store;

        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security violation');

        $connector->syncProduct($storeOverride);
    }
}
