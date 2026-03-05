<?php

namespace Tests\Unit\Services\Customer\Template;

use App\Jobs\Shopify\SyncShopifyBaseProductJob;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Services\Customer\Template\VendorDesignTemplateStoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class VendorDesignTemplateStoreServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_store_settings_dispatches_generic_job_for_non_woocommerce()
    {
        Queue::fake();

        // Arrange
        $vendorId = 1;
        $template = Mockery::mock(VendorDesignTemplate::class);
        $template->shouldReceive('getAttribute')->with('id')->andReturn(10);

        $storeId = 1;
        $data = [
            'store_id' => $storeId,
            'name' => 'Test Store Template',
        ];

        // Mock Store Channel (Generic)
        $channel = Mockery::mock(\App\Models\StoreChannels\StoreChannel::class)->makePartial();
        $channel->code = 'shopify';

        // Mock Connected Store
        $connectedStore = Mockery::mock(\App\Models\Customer\Store\VendorConnectedStore::class)->makePartial();
        $connectedStore->storeChannel = $channel;

        // Mock Store Override
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(100);
        $storeOverride->shouldReceive('getAttribute')->with('name')->andReturn('Test Store Template');
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);

        $storeOverride->shouldReceive('loadMissing')->with('connectedStore.storeChannel');

        $storeOverride->shouldReceive('update')
            ->once()
            ->with([
                'sync_status' => 'pending',
                'sync_error' => null,
            ]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) {
                return $closure();
            });

        $service = Mockery::mock(VendorDesignTemplateStoreService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('updateOrCreateStoreOverride')
            ->once()
            ->with($template, $data, $vendorId)
            ->andReturn($storeOverride);

        // Act
        $service->updateStoreSettings($template, $data, $vendorId, true);

        // Assert
        Queue::assertPushed(SyncShopifyBaseProductJob::class);
        Queue::assertNotPushed(\App\Jobs\WooCommerce\SyncWooBaseProductJob::class);
    }

    public function test_update_store_settings_dispatches_woo_job_for_woocommerce()
    {
        Queue::fake();

        // Arrange
        $vendorId = 1;
        $template = Mockery::mock(VendorDesignTemplate::class);
        $template->shouldReceive('getAttribute')->with('id')->andReturn(10);

        $storeId = 1;
        $data = ['store_id' => $storeId];

        // Mock Store Channel (WooCommerce)
        $channel = Mockery::mock(\App\Models\StoreChannels\StoreChannel::class)->makePartial();
        $channel->code = 'woocommerce';

        // Mock Connected Store
        $connectedStore = Mockery::mock(\App\Models\Customer\Store\VendorConnectedStore::class)->makePartial();
        $connectedStore->storeChannel = $channel;

        // Mock Store Override
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('id')->andReturn(100);
        $storeOverride->shouldReceive('getAttribute')->with('connectedStore')->andReturn($connectedStore);

        $storeOverride->shouldReceive('loadMissing')->with('connectedStore.storeChannel');

        $storeOverride->shouldReceive('update')
            ->once()
            ->with([
                'sync_status' => 'pending',
                'sync_error' => null,
            ]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) {
                return $closure();
            });

        $service = Mockery::mock(VendorDesignTemplateStoreService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('updateOrCreateStoreOverride')
            ->once()
            ->with($template, $data, $vendorId)
            ->andReturn($storeOverride);

        // Act
        $service->updateStoreSettings($template, $data, $vendorId, true);

        // Assert
        Queue::assertPushed(\App\Jobs\WooCommerce\SyncWooBaseProductJob::class);
        Queue::assertNotPushed(SyncShopifyBaseProductJob::class);
    }

    public function test_update_store_settings_does_not_dispatch_job_when_sync_is_false()
    {
        Queue::fake();

        // Arrange
        $vendorId = 1;
        $template = Mockery::mock(VendorDesignTemplate::class);
        $template->shouldReceive('getAttribute')->with('id')->andReturn(10);

        $storeId = 1;
        $data = ['store_id' => $storeId];

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();

        // Expect update NOT to be called for sync_status
        $storeOverride->shouldReceive('update')->never();

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) {
                return $closure();
            });

        $service = Mockery::mock(VendorDesignTemplateStoreService::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('updateOrCreateStoreOverride')
            ->once()
            ->with($template, $data, $vendorId)
            ->andReturn($storeOverride);

        // Act
        $service->updateStoreSettings($template, $data, $vendorId, false);

        // Assert
        Queue::assertNotPushed(SyncShopifyBaseProductJob::class);
        Queue::assertNotPushed(\App\Jobs\WooCommerce\SyncWooBaseProductJob::class);
    }

    public function test_update_store_settings_does_not_dispatch_job_when_link_only_is_true()
    {
        Queue::fake();

        // Arrange
        $vendorId = 1;
        $template = Mockery::mock(VendorDesignTemplate::class);
        $template->shouldReceive('getAttribute')->with('id')->andReturn(10);

        $storeId = 1;
        $data = ['store_id' => $storeId];

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->shouldReceive('getAttribute')->with('is_link_only')->andReturn(true);

        // Expect update NOT to be called for sync_status
        $storeOverride->shouldReceive('update')->never();

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) {
                return $closure();
            });

        $service = Mockery::mock(VendorDesignTemplateStoreService::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('updateOrCreateStoreOverride')
            ->once()
            ->with($template, $data, $vendorId)
            ->andReturn($storeOverride);

        // Act
        $service->updateStoreSettings($template, $data, $vendorId, true);

        // Assert
        Queue::assertNotPushed(SyncShopifyBaseProductJob::class);
        Queue::assertNotPushed(\App\Jobs\WooCommerce\SyncWooBaseProductJob::class);
    }
}
