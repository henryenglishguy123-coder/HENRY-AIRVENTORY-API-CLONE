<?php

namespace Tests\Unit\Jobs\WooCommerce;

use App\Jobs\WooCommerce\DeleteWooCommerceWebhooksJob;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DeleteWooCommerceWebhooksJobTest extends TestCase
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
    public function test_handle_deletes_webhooks()
    {
        // Arrange
        $storeId = 1;

        // Overload mocks MUST be defined first
        $storeMock = Mockery::mock('overload:App\Models\Customer\Store\VendorConnectedStore');
        $channelMock = Mockery::mock('overload:App\Models\StoreChannels\StoreChannel');

        // Static expectations
        $storeMock->shouldReceive('where')->with('id', $storeId)->andReturnSelf();
        $storeMock->shouldReceive('where')->with('channel', 'woocommerce')->andReturnSelf();
        $storeMock->shouldReceive('firstOrFail')->andReturn($storeMock); // Return self/mock as the instance

        $channelMock->shouldReceive('where')->with('code', 'woocommerce')->andReturnSelf();
        $channelMock->shouldReceive('firstOrFail')->andReturn($channelMock);

        // Mock Connector
        $connector = Mockery::mock(WooCommerceConnector::class);
        $connector->shouldReceive('deleteWebhooks')->with(Mockery::type(VendorConnectedStore::class))->once();

        $factory = Mockery::mock(StoreConnectorFactory::class);
        $factory->shouldReceive('make')->with(Mockery::type(StoreChannel::class))->andReturn($connector);

        Log::shouldReceive('info')->twice(); // Start + Success

        // Act
        $job = new DeleteWooCommerceWebhooksJob($storeId);
        $job->handle($factory);

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_handle_logs_warning_if_store_not_found()
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
        Log::shouldReceive('warning')->with('Store or WooCommerce store channel not found for webhook deletion, aborting', Mockery::subset(['store_id' => $storeId]))->once();

        // Act
        $job = new DeleteWooCommerceWebhooksJob($storeId);
        $job->handle($factory);

        // Assert: No exception thrown
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
        Log::shouldReceive('error')->with('Failed to delete WooCommerce webhooks', Mockery::any())->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB Error');

        $job = new DeleteWooCommerceWebhooksJob($storeId);
        $job->handle($factory);
    }
}
