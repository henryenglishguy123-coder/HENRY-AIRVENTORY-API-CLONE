<?php

namespace Tests\Feature\Jobs\WooCommerce;

use App\Enums\Store\StoreConnectionStatus;
use App\Jobs\WooCommerce\CheckWooCommerceConnectionJob;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class CheckWooCommerceConnectionJobTest extends TestCase
{
    use CreatesTestTables, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        // Create a vendor first
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

        StoreChannel::create([
            'name' => 'WooCommerce',
            'code' => 'woocommerce',
            'status' => 'active',
        ]);
    }

    public function test_handle_marks_store_disconnected_on_verification_failure()
    {
        $store = VendorConnectedStore::create([
            'vendor_id' => 1,
            'channel' => 'woocommerce',
            'store_identifier' => 'test-store',
            'link' => 'https://test-store.com',
            'status' => 'connected',
            'token' => encrypt([
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ]),
        ]);

        $mockConnector = Mockery::mock(WooCommerceConnector::class);
        $mockConnector->shouldReceive('verify')
            ->once()
            ->andReturn(false);

        $mockFactory = Mockery::mock(StoreConnectorFactory::class);
        $mockFactory->shouldReceive('make')
            ->andReturn($mockConnector);

        $job = new CheckWooCommerceConnectionJob($store->id);
        $job->handle($mockFactory);

        $store->refresh();
        $this->assertEquals(StoreConnectionStatus::DISCONNECTED, $store->status);
        $this->assertStringContainsString('Connection lost', $store->error_message);
    }

    public function test_handle_keeps_store_connected_on_success()
    {
        $store = VendorConnectedStore::create([
            'vendor_id' => 1,
            'channel' => 'woocommerce',
            'store_identifier' => 'test-store-2',
            'link' => 'https://test-store-2.com',
            'status' => 'connected',
            'token' => encrypt([
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ]),
        ]);

        $mockConnector = Mockery::mock(WooCommerceConnector::class);
        $mockConnector->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $mockFactory = Mockery::mock(StoreConnectorFactory::class);
        $mockFactory->shouldReceive('make')
            ->andReturn($mockConnector);

        $job = new CheckWooCommerceConnectionJob($store->id);
        $job->handle($mockFactory);

        $store->refresh();
        $this->assertEquals(StoreConnectionStatus::CONNECTED, $store->status);
    }
}
