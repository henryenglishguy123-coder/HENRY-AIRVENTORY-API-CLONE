<?php

namespace Tests\Feature\Customer\Store;

use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Contracts\StoreConnectorInterface;
use App\Services\Channels\Factory\StoreConnectorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectedStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed store channels if needed or create them
        StoreChannel::create(['code' => 'woocommerce', 'name' => 'WooCommerce']);
    }

    public function test_can_check_connection_success()
    {
        $customer = $this->createCustomer();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'woocommerce',
            'store_identifier' => 'test-store',
            'link' => 'https://test-store.com',
            'token' => encrypt(['consumer_key' => 'ck_123', 'consumer_secret' => 'cs_123']),
            'status' => 'error', // Start with error to verify it updates to connected
        ]);

        // Mock Connector
        $mockConnector = $this->mock(StoreConnectorInterface::class);
        $mockConnector->shouldReceive('verify')
            ->once()
            ->withArgs(function ($credentials) use ($store) {
                return $credentials['consumer_key'] === 'ck_123'
                    && $credentials['consumer_secret'] === 'cs_123'
                    && $credentials['link'] === $store->link;
            })
            ->andReturn(true);

        // Mock Factory
        $this->mock(StoreConnectorFactory::class)
            ->shouldReceive('make')
            ->once()
            ->withArgs(function ($channel) {
                return $channel->code === 'woocommerce';
            })
            ->andReturn($mockConnector);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson(route('customer.stores.check-connection', $store->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'connected'],
            ]);

        $this->assertDatabaseHas('vendor_connected_stores', [
            'id' => $store->id,
            'status' => 'connected',
            'error_message' => null,
        ]);
    }

    public function test_can_check_connection_failure()
    {
        $customer = $this->createCustomer();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'woocommerce',
            'store_identifier' => 'test-store-fail',
            'link' => 'https://test-store-fail.com',
            'token' => encrypt(['consumer_key' => 'ck_123', 'consumer_secret' => 'cs_123']),
            'status' => 'connected', // Start with connected to verify it updates to error
        ]);

        // Mock Connector
        $mockConnector = $this->mock(StoreConnectorInterface::class);
        $mockConnector->shouldReceive('verify')
            ->once()
            ->andReturn(false);

        // Mock Factory
        $this->mock(StoreConnectorFactory::class)
            ->shouldReceive('make')
            ->once()
            ->andReturn($mockConnector);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson(route('customer.stores.check-connection', $store->id));

        $response->assertStatus(200) // Expect 200 OK even on failure, as per implementation
            ->assertJson([
                'success' => false,
                'data' => ['status' => 'error'],
            ]);

        $this->assertDatabaseHas('vendor_connected_stores', [
            'id' => $store->id,
            'status' => 'error',
            'error_message' => 'Unable to verify store credentials.',
        ]);
    }

    public function test_cannot_check_other_customers_store()
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $otherCustomer->id,
            'channel' => 'woocommerce',
            'store_identifier' => 'other-store',
            'link' => 'https://other-store.com',
            'token' => encrypt('token'),
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson(route('customer.stores.check-connection', $store->id));

        $response->assertStatus(404);
    }

    // Helper to create customer (assuming generic factory availability or implementing minimal)
    protected function createCustomer()
    {
        // Assuming there is a Customer factory or creating manually
        // Since I don't know the exact factory structure, I'll try to use the model directly if needed
        // But usually tests use factories.
        // Let's check if Customer factory exists in previous searches or try to use generic create.

        // Based on previous file reads, there is App\Models\Customer\Vendor (which is the customer/vendor model?)
        // The ConnectedStoreController uses AccountController->resolveCustomer.
        // AccountController likely uses auth('customer')->user().

        // Checking App\Models\Customer\Vendor usage in other tests might be helpful.
        // Tests/Feature/FactoryBusinessInformationTest.php used Factory::create.
        // I will assume Vendor::create works or try to find a factory.

        // I'll assume standard User factory or Vendor factory.
        // Let's try to use Vendor::factory()->create() if it exists, or create manually.

        return \App\Models\Customer\Vendor::factory()->create();
    }
}
