<?php

namespace Tests\Unit\Jobs\WooCommerce;

use App\Jobs\WooCommerce\RegisterWooCommerceWebhooksJob;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RegisterWooCommerceWebhooksJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_handle_registers_webhooks()
    {
        // Arrange
        $storeId = 1;

        // Overload mocks MUST be defined first
        $storeMock = Mockery::mock('overload:App\Models\Customer\Store\VendorConnectedStore');
        $channelMock = Mockery::mock('overload:App\Models\StoreChannels\StoreChannel');

        // Create instances (which are now mocked)
        $store = new VendorConnectedStore;
        $channel = new StoreChannel;

        // Configure instance behavior (via the overload mock, which controls all instances)
        // Wait, normally expectations on the overload mock apply to instances.
        // But for static methods, we expect them on the overload mock too.
        // To distinguish, usually we return a specific mock from static call if we want separate control.
        // But let's try configuring the overload mock to handle everything.

        $storeMock->shouldReceive('getAttribute')->with('id')->andReturn($storeId);
        $storeMock->shouldReceive('getAttribute')->with('token')->andReturn(encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']));

        $channelMock->shouldReceive('getAttribute')->with('code')->andReturn('woocommerce');

        // Static expectations
        $storeMock->shouldReceive('where')->with('id', $storeId)->andReturnSelf();
        $storeMock->shouldReceive('where')->with('channel', 'woocommerce')->andReturnSelf();
        $storeMock->shouldReceive('firstOrFail')->andReturn($storeMock); // Return self/mock as the instance

        $channelMock->shouldReceive('where')->with('code', 'woocommerce')->andReturnSelf();
        $channelMock->shouldReceive('firstOrFail')->andReturn($channelMock);

        // Mock Connector
        // Connector expects VendorConnectedStore. The overloaded mock satisfies this?
        // Since we are overloading, the class definition is replaced by Mockery.
        // So passing $storeMock (which is the mock object) should work if it mimics the class.

        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('registerWebhooks')->with(Mockery::type(VendorConnectedStore::class))->once();

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->with(Mockery::type(StoreChannel::class))->andReturn($connector);

        Log::shouldReceive('info')->twice(); // Start + Success

        // Act
        $job = new RegisterWooCommerceWebhooksJob($storeId);
        $job->handle($factory);

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_handle_fails_if_store_not_found()
    {
        // Arrange
        $storeId = 1;

        // Mock Store finding failure
        $storeMock = Mockery::mock('overload:App\Models\Customer\Store\VendorConnectedStore');
        $storeMock->shouldReceive('where')->andReturnSelf();
        $storeMock->shouldReceive('where')->andReturnSelf();
        $storeMock->shouldReceive('firstOrFail')->andThrow(new ModelNotFoundException);

        $factory = Mockery::mock(StoreConnectorFactory::class);

        Log::shouldReceive('info')->once(); // Start
        Log::shouldReceive('warning')->with('Store not found for webhook registration, aborting', ['store_id' => $storeId])->once();

        // Mock job without calling constructor to avoid onQueue issue
        $job = Mockery::mock(RegisterWooCommerceWebhooksJob::class)->makePartial();
        $job->storeId = $storeId;
        $job->queue = 'integrations';
        $job->shouldReceive('fail')->once()->with(Mockery::type(ModelNotFoundException::class));

        $job->handle($factory);

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_handle_throws_exception_on_general_error()
    {
        // Arrange
        $storeId = 1;

        // Mock Store finding failure with generic exception
        $storeMock = Mockery::mock('overload:App\Models\Customer\Store\VendorConnectedStore');
        $storeMock->shouldReceive('where')->andThrow(new \Exception('DB Error'));

        $factory = Mockery::mock(StoreConnectorFactory::class);

        Log::shouldReceive('info')->once(); // Start
        Log::shouldReceive('error')->with('Failed to register WooCommerce webhooks', Mockery::any())->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB Error');

        $job = new RegisterWooCommerceWebhooksJob($storeId);
        $job->handle($factory);
    }
}
