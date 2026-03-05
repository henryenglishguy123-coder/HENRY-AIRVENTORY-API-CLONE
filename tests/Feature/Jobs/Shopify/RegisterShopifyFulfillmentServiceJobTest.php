<?php

namespace Tests\Feature\Jobs\Shopify;

use App\Jobs\Shopify\RegisterShopifyFulfillmentServiceJob;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\Shopify\ShopifyFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegisterShopifyFulfillmentServiceJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.shopify.api_version', '2024-01');
    }

    public function test_handle_registers_new_service_and_updates_store()
    {
        // Arrange
        $storeIdentifier = 'shopify-test-store.myshopify.com';
        $accessToken = 'test-access-token';
        $encryptedToken = encrypt(['access_token' => $accessToken]);

        $store = new VendorConnectedStore;
        $store->vendor_id = 1;
        $store->channel = 'shopify';
        $store->store_identifier = $storeIdentifier;
        $store->token = $encryptedToken;
        $store->status = 'connected';
        $store->save();

        // Mock Shopify API
        Http::fake([
            // 1. Check existing services (GET) -> Return empty list
            "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json?scope=all" => Http::response([
                'fulfillment_services' => [],
            ], 200),

            // 2. Create service (POST) -> Return success
            "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json" => Http::response([
                'fulfillment_service' => [
                    'id' => 'service_123',
                    'location_id' => 'location_456',
                    'name' => 'Airventory Fulfillment',
                    'handle' => 'airventory-fulfillment',
                ],
            ], 201),
        ]);

        // Act
        // We use the real service here, relying on Http::fake
        $job = new RegisterShopifyFulfillmentServiceJob($storeIdentifier);
        $job->handle(app(ShopifyFulfillmentService::class));

        // Assert
        $store->refresh();
        $this->assertEquals('service_123', $store->additional_data['fulfillment_service_id']);
        $this->assertEquals('location_456', $store->additional_data['location_id']);

        // Verify HTTP requests were made
        Http::assertSent(function ($request) use ($storeIdentifier) {
            return $request->url() === "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json?scope=all" &&
                   $request->method() === 'GET';
        });

        Http::assertSent(function ($request) use ($storeIdentifier) {
            return $request->url() === "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json" &&
                   $request->method() === 'POST' &&
                   $request['fulfillment_service']['name'] === 'Airventory Fulfillment';
        });
    }

    public function test_handle_uses_existing_service_and_updates_store()
    {
        // Arrange
        $storeIdentifier = 'shopify-existing.myshopify.com';
        $accessToken = 'test-access-token';
        $encryptedToken = encrypt(['access_token' => $accessToken]);

        $store = new VendorConnectedStore;
        $store->vendor_id = 1;
        $store->channel = 'shopify';
        $store->store_identifier = $storeIdentifier;
        $store->token = $encryptedToken;
        $store->status = 'connected';
        $store->save();

        // Mock Shopify API
        Http::fake([
            // 1. Check existing services (GET) -> Return existing service
            "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json?scope=all" => Http::response([
                'fulfillment_services' => [
                    [
                        'id' => 'service_existing_999',
                        'location_id' => 'location_existing_888',
                        'name' => 'Airventory Fulfillment', // Matches SERVICE_NAME constant
                        'handle' => 'airventory-fulfillment',
                    ],
                ],
            ], 200),
        ]);

        // Act
        $job = new RegisterShopifyFulfillmentServiceJob($storeIdentifier);
        $job->handle(app(ShopifyFulfillmentService::class));

        // Assert
        $store->refresh();
        $this->assertEquals('service_existing_999', $store->additional_data['fulfillment_service_id']);
        $this->assertEquals('location_existing_888', $store->additional_data['location_id']);

        // Verify POST was NOT made
        Http::assertNotSent(function ($request) {
            return $request->method() === 'POST';
        });
    }

    public function test_handle_fails_gracefully_on_api_error()
    {
        // Arrange
        $storeIdentifier = 'shopify-error.myshopify.com';
        $accessToken = 'test-access-token';
        $encryptedToken = encrypt(['access_token' => $accessToken]);

        $store = new VendorConnectedStore;
        $store->vendor_id = 1;
        $store->channel = 'shopify';
        $store->store_identifier = $storeIdentifier;
        $store->token = $encryptedToken;
        $store->status = 'connected';
        $store->save();

        // Mock Shopify API
        Http::fake([
            // 1. Check existing services (GET) -> Return empty
            "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json?scope=all" => Http::response([
                'fulfillment_services' => [],
            ], 200),

            // 2. Create service (POST) -> Return Error
            "https://{$storeIdentifier}/admin/api/2024-01/fulfillment_services.json" => Http::response([
                'errors' => 'Something went wrong',
            ], 422),
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to register Fulfillment Service');

        $job = new RegisterShopifyFulfillmentServiceJob($storeIdentifier);
        $job->handle(app(ShopifyFulfillmentService::class));
    }
}
