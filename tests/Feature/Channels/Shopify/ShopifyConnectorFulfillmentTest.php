<?php

namespace Tests\Feature\Channels\Shopify;

use App\Enums\Store\StoreConnectionStatus;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Services\Channels\Shopify\ShopifyDataService;
use App\Services\Channels\Shopify\ShopifyFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ShopifyConnectorFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected ShopifyConnector $connector;

    protected $dataServiceMock;

    protected $fulfillmentServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Manual Schema Creation for missing tables in SQLite
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('mobile')->nullable();
                $table->string('password')->nullable();
                $table->string('account_status')->nullable();
                $table->string('source')->nullable();
                $table->string('social_login_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->decimal('balance', 10, 2)->default(0);
                $table->string('currency')->default('USD');
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_design_template')) {
            \Illuminate\Support\Facades\Schema::create('catalog_design_template', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_templates')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_templates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->foreignId('catalog_design_template_id');
                $table->timestamps();
            });
        }

        $channel = Mockery::mock(StoreChannel::class);

        $this->dataServiceMock = Mockery::mock(ShopifyDataService::class);
        // We don't bind dataServiceMock to container because ShopifyConnector constructor takes it.
        // But we need to handle other dependencies if any.

        $this->fulfillmentServiceMock = Mockery::mock(ShopifyFulfillmentService::class);
        $this->app->instance(ShopifyFulfillmentService::class, $this->fulfillmentServiceMock);

        $this->connector = new ShopifyConnector($channel, $this->dataServiceMock);

        Config::set('services.shopify.key', 'test_key');
        Config::set('services.shopify.secret', 'test_secret');
        Config::set('services.shopify.api_version', '2024-01');
    }

    public function test_ensure_fulfillment_service_registers_when_handle_is_missing()
    {
        // 1. Create Models
        $vendor = Vendor::factory()->create();

        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'link' => 'https://test-shop.myshopify.com',
            'token' => encrypt([
                'access_token' => 'shpat_token',
                'shop' => 'test-shop.myshopify.com',
            ]),
            'currency' => 'USD',
            'status' => StoreConnectionStatus::CONNECTED,
            'additional_data' => [
                'fulfillment_service_id' => 'service_123',
                'location_id' => 'location_456',
                // Handle is MISSING
            ],
        ]);

        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendor->id]);

        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => '12345',
            'status' => 'active',
        ]);

        // 2. Mock Expectations
        // We expect register to be called because handle is missing
        $this->fulfillmentServiceMock->shouldReceive('register')
            ->once()
            ->with('test-shop.myshopify.com', 'shpat_token')
            ->andReturn([
                'service_id' => 'service_123',
                'location_id' => 'location_456',
                'handle' => 'airventory-fulfillment-handle',
            ]);

        // Mock DataService to avoid errors in syncProduct
        $this->dataServiceMock->shouldReceive('ensureProductRelationships');
        $this->dataServiceMock->shouldReceive('prepareProductData')->andReturn(['product' => ['title' => 'T']]);
        $this->dataServiceMock->shouldReceive('getOrderedImages')->andReturn([]);
        $this->dataServiceMock->shouldReceive('prepareVariationsData')->andReturn(['create' => [], 'update' => []]);
        $this->dataServiceMock->shouldReceive('getSortedOptionNames')->andReturn([]);
        $this->dataServiceMock->shouldReceive('getVariantOptions')->andReturn([]);

        // Mock HTTP for syncBaseProduct calls
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/products/12345.json' => Http::response(['product' => ['id' => 12345, 'title' => 'Existing Title']], 200),
            '*' => Http::response(['product' => []], 200), // Return empty product array to avoid null error if mismatch
        ]);

        // 3. Run Sync (which calls ensureFulfillmentServiceRegistered)
        try {
            $this->connector->syncProduct($storeOverride);
        } catch (\Exception $e) {
            // Ignore other errors, we care about the call to register
        }

        // 4. Assert Store Updated
        $store->refresh();
        $this->assertEquals('airventory-fulfillment-handle', $store->additional_data['fulfillment_service_handle']);
    }

    public function test_ensure_fulfillment_service_does_not_register_when_handle_is_present()
    {
        // 1. Create Models
        $vendor = Vendor::factory()->create();

        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'link' => 'https://test-shop.myshopify.com',
            'token' => encrypt([
                'access_token' => 'shpat_token',
                'shop' => 'test-shop.myshopify.com',
            ]),
            'currency' => 'USD',
            'status' => StoreConnectionStatus::CONNECTED,
            'additional_data' => [
                'fulfillment_service_id' => 'service_123',
                'location_id' => 'location_456',
                'fulfillment_service_handle' => 'existing-handle', // Handle is PRESENT
            ],
        ]);

        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendor->id]);

        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => '12345',
            'status' => 'active',
        ]);

        // 2. Mock Expectations
        // We expect register NOT to be called
        $this->fulfillmentServiceMock->shouldReceive('register')->never();

        // Mock DataService to avoid errors in syncProduct
        $this->dataServiceMock->shouldReceive('ensureProductRelationships');
        $this->dataServiceMock->shouldReceive('prepareProductData')->andReturn(['product' => ['title' => 'T']]);
        $this->dataServiceMock->shouldReceive('getOrderedImages')->andReturn([]);
        $this->dataServiceMock->shouldReceive('prepareVariationsData')->andReturn(['create' => [], 'update' => []]);
        $this->dataServiceMock->shouldReceive('getSortedOptionNames')->andReturn([]);
        $this->dataServiceMock->shouldReceive('getVariantOptions')->andReturn([]);

        // Mock HTTP for syncBaseProduct calls
        Http::fake([
            'https://test-shop.myshopify.com/admin/api/*/products/12345.json' => Http::response(['product' => ['id' => 12345, 'title' => 'Existing Title']], 200),
            '*' => Http::response(['product' => []], 200),
        ]);

        // 3. Run Sync
        try {
            $this->connector->syncProduct($storeOverride);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    public function test_sync_product_publishes_to_markets_on_creation()
    {
        // 1. Create Models
        $vendor = Vendor::factory()->create();
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'link' => 'https://test-shop.myshopify.com',
            'token' => encrypt(['access_token' => 'shpat_token', 'shop' => 'test-shop.myshopify.com']),
            'currency' => 'USD',
            'status' => StoreConnectionStatus::CONNECTED,
            'additional_data' => [
                'fulfillment_service_id' => 'service_123',
                'location_id' => 'location_456',
                'fulfillment_service_handle' => 'handle',
            ],
        ]);

        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendor->id]);
        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => null, // New product
            'status' => 'active',
        ]);

        // 2. Mock DataService
        $this->dataServiceMock->shouldReceive('ensureProductRelationships');
        $this->dataServiceMock->shouldReceive('prepareProductData')->andReturn(['product' => ['title' => 'New Product']]);
        $this->dataServiceMock->shouldReceive('getOrderedImages')->andReturn([]);
        $this->dataServiceMock->shouldReceive('prepareVariationsData')->andReturn(['create' => [], 'update' => []]);
        $this->dataServiceMock->shouldReceive('getSortedOptionNames')->andReturn([]);
        $this->dataServiceMock->shouldReceive('getVariantOptions')->andReturn([]);
        $this->fulfillmentServiceMock->shouldReceive('register')->never(); // Handle present

        // 3. Mock HTTP
        Http::fake([
            // Create product
            '*/products.json' => Http::response(['product' => ['id' => 99999, 'title' => 'New Product']], 201),
            // GraphQL calls for publishing (Sequence: 1. Get Publications, 2. Publish)
            '*/graphql.json' => Http::sequence()
                ->push(['data' => ['publications' => ['edges' => [['node' => ['id' => 'pub_1', 'name' => 'Online Store']]]]]], 200)
                ->push(['data' => ['publishablePublish' => ['userErrors' => [], 'publishable' => ['availablePublicationsCount' => ['count' => 1]]]]], 200),
            '*' => Http::response([], 200),
        ]);

        // 4. Run Sync
        $this->connector->syncProduct($storeOverride);

        // 5. Assertions
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graphql.json') &&
                   str_contains($request->body(), 'publications(first: 50, after: $after)');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graphql.json') &&
                   str_contains($request->body(), 'publishablePublish');
        });

        $storeOverride->refresh();
        $this->assertEquals('99999', $storeOverride->external_product_id);
    }

    public function test_sync_product_does_not_publish_to_markets_on_update()
    {
        // 1. Create Models
        $vendor = Vendor::factory()->create();
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'link' => 'https://test-shop.myshopify.com',
            'token' => encrypt(['access_token' => 'shpat_token', 'shop' => 'test-shop.myshopify.com']),
            'currency' => 'USD',
            'status' => StoreConnectionStatus::CONNECTED,
            'additional_data' => [
                'fulfillment_service_id' => 'service_123',
                'location_id' => 'location_456',
                'fulfillment_service_handle' => 'handle',
            ],
        ]);

        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendor->id]);
        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => '12345', // Existing product
            'status' => 'active',
        ]);

        // 2. Mock DataService
        $this->dataServiceMock->shouldReceive('ensureProductRelationships');
        $this->dataServiceMock->shouldReceive('prepareProductData')->andReturn(['product' => ['title' => 'Updated Product']]);
        $this->dataServiceMock->shouldReceive('getOrderedImages')->andReturn([]);
        $this->dataServiceMock->shouldReceive('prepareVariationsData')->andReturn(['create' => [], 'update' => []]);
        $this->dataServiceMock->shouldReceive('getSortedOptionNames')->andReturn([]);
        $this->dataServiceMock->shouldReceive('getVariantOptions')->andReturn([]);
        $this->fulfillmentServiceMock->shouldReceive('register')->never();

        // 3. Mock HTTP
        Http::fake([
            // Update existing product: GET then PUT
            '*/products/12345.json' => Http::response(['product' => ['id' => 12345, 'title' => 'Old Title', 'tags' => '', 'body_html' => '', 'vendor' => '', 'product_type' => '', 'handle' => '', 'status' => '', 'published_scope' => '']], 200),
            // Images fetch
            '*/products/12345/images.json' => Http::response(['images' => []], 200),
            // Explicitly fail if GraphQL is called
            '*/graphql.json' => function ($request) {
                // We don't want any GraphQL calls
                return Http::response([], 500);
            },
            '*' => Http::response([], 200),
        ]);

        // 4. Run Sync
        $this->connector->syncProduct($storeOverride);

        // 5. Assertions
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'graphql.json');
        });
    }

    public function test_sync_product_pagination_in_publish_to_markets()
    {
        // 1. Create Models
        $vendor = Vendor::factory()->create();
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'link' => 'https://test-shop.myshopify.com',
            'token' => encrypt(['access_token' => 'shpat_token', 'shop' => 'test-shop.myshopify.com']),
            'currency' => 'USD',
            'status' => StoreConnectionStatus::CONNECTED,
            'additional_data' => [
                'fulfillment_service_id' => 'service_123',
                'location_id' => 'location_456',
                'fulfillment_service_handle' => 'handle',
            ],
        ]);

        $template = VendorDesignTemplate::factory()->create(['vendor_id' => $vendor->id]);
        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => null, // New product
            'status' => 'active',
        ]);

        // 2. Mock DataService
        $this->dataServiceMock->shouldReceive('ensureProductRelationships');
        $this->dataServiceMock->shouldReceive('prepareProductData')->andReturn(['product' => ['title' => 'New Product']]);
        $this->dataServiceMock->shouldReceive('getOrderedImages')->andReturn([]);
        $this->dataServiceMock->shouldReceive('prepareVariationsData')->andReturn(['create' => [], 'update' => []]);
        $this->dataServiceMock->shouldReceive('getSortedOptionNames')->andReturn([]);
        $this->dataServiceMock->shouldReceive('getVariantOptions')->andReturn([]);

        $this->fulfillmentServiceMock->shouldReceive('register')->never();

        // 3. Mock HTTP for Pagination
        Http::fake([
            // Create product
            '*/products.json' => Http::response(['product' => ['id' => 99999, 'title' => 'New Product']], 201),

            // GraphQL calls for publishing
            '*/graphql.json' => Http::sequence()
                // Page 1
                ->push([
                    'data' => [
                        'publications' => [
                            'edges' => [['node' => ['id' => 'pub_1', 'name' => 'Pub 1']]],
                            'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor_1'],
                        ],
                    ],
                ], 200)
                // Page 2
                ->push([
                    'data' => [
                        'publications' => [
                            'edges' => [['node' => ['id' => 'pub_2', 'name' => 'Pub 2']]],
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'cursor_2'],
                        ],
                    ],
                ], 200)
                // Mutation Response
                ->push([
                    'data' => [
                        'publishablePublish' => [
                            'userErrors' => [],
                            'publishable' => ['availablePublicationsCount' => ['count' => 2]],
                        ],
                    ],
                ], 200),
            '*' => Http::response([], 200),
        ]);

        // 4. Run Sync
        $this->connector->syncProduct($storeOverride);

        // 5. Assertions
        // Verify 4 HTTP calls: 1 Product Create, 2 GraphQL Pages, 1 Mutation
        Http::assertSentCount(4);

        // Verify Mutation contained both IDs
        Http::assertSent(function ($request) {
            if (str_contains($request->body(), 'publishablePublish')) {
                $body = $request->body();

                return str_contains($body, 'pub_1') && str_contains($body, 'pub_2');
            }

            return false;
        });
    }
}
