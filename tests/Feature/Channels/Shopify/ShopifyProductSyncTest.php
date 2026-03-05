<?php

namespace Tests\Feature\Channels\Shopify;

use App\Enums\Store\StoreConnectionStatus;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Services\Channels\Shopify\ShopifyDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ShopifyProductSyncTest extends TestCase
{
    use RefreshDatabase;

    protected ShopifyConnector $connector;

    protected $dataServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Mockery::mock(StoreChannel::class);

        $this->dataServiceMock = Mockery::mock(ShopifyDataService::class);
        $this->app->instance(ShopifyDataService::class, $this->dataServiceMock);

        $this->connector = new ShopifyConnector($channel, $this->dataServiceMock);

        Config::set('services.shopify.key', 'test_key');
        Config::set('services.shopify.secret', 'test_secret');
        Config::set('services.shopify.api_version', '2024-01');
    }

    public function test_sync_product_links_existing_variants_by_options()
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
        ]);

        $template = VendorDesignTemplate::factory()->create([
            'vendor_id' => $vendor->id,
        ]);

        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => '12345',
            'status' => 'active',
        ]);

        $catalogProduct = CatalogProduct::factory()->create();

        $variant = VendorDesignTemplateStoreVariant::create([
            'vendor_design_template_store_id' => $storeOverride->id,
            'catalog_product_id' => $catalogProduct->id,
            'sku' => 'NEW-SKU',
            'external_variant_id' => null,
            'is_enabled' => true,
        ]);

        // 2. Mock DataService Expectations
        $this->dataServiceMock->shouldReceive('ensureProductRelationships');
        $this->dataServiceMock->shouldReceive('prepareProductData')->andReturn(['product' => ['title' => 'T']]);
        $this->dataServiceMock->shouldReceive('getOrderedImages')->andReturn([]);

        // For Reconcile
        $this->dataServiceMock->shouldReceive('getSortedOptionNames')->andReturn(['Option1', 'Option2']);
        $this->dataServiceMock->shouldReceive('getVariantOptions')
            ->with(Mockery::type(VendorDesignTemplateStoreVariant::class), ['Option1', 'Option2'])
            ->andReturn(['Blue', 'Large']);

        $this->dataServiceMock->shouldReceive('prepareVariationsData')->andReturn([
            'create' => [],
            'update' => [['id' => 999, 'sku' => 'NEW-SKU']],
        ]);

        // 3. Mock HTTP Requests
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'products/12345.json') && $method === 'GET') {
                return Http::response(['product' => ['id' => 12345, 'title' => 'T']], 200);
            }

            // Reconcile: Get Variants
            if (str_contains($url, 'products/12345/variants.json') && $method === 'GET') {
                return Http::response([
                    'variants' => [
                        [
                            'id' => 999,
                            'sku' => 'OLD-SKU', // Mismatch SKU
                            'option1' => 'Blue',
                            'option2' => 'Large',
                            // Match Options
                        ],
                    ],
                ], 200);
            }

            // Sync Variations: PUT (Batch Update via Product)
            if (str_contains($url, 'products/12345.json') && $method === 'PUT' && isset($request['product']['variants'])) {
                return Http::response(['product' => ['id' => 12345]], 200);
            }

            if (str_contains($url, 'products/12345.json') && $method === 'PUT') {
                return Http::response(['product' => ['id' => 12345]], 200);
            }

            return Http::response([], 200);
        });

        // 4. Run Sync
        $this->connector->syncProduct($storeOverride);

        // 5. Assert Database Changes
        $this->assertDatabaseHas('vendor_design_template_store_variants', [
            'id' => $variant->id,
            'external_variant_id' => 999,
        ]);
    }

    public function test_sync_product_preserves_images_and_adds_missing_ones()
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
        ]);

        $template = VendorDesignTemplate::factory()->create([
            'vendor_id' => $vendor->id,
        ]);

        $storeOverride = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_connected_store_id' => $store->id,
            'vendor_design_template_id' => $template->id,
            'external_product_id' => '12345',
            'status' => 'active',
        ]);

        // 2. Mock DataService Expectations
        $this->dataServiceMock->shouldReceive('ensureProductRelationships')->once();

        $this->dataServiceMock->shouldReceive('prepareProductData')->once()->andReturn([
            'product' => [
                'title' => 'New Title',
                'body_html' => 'Desc',
                'images' => [
                    ['src' => 'http://local/image1.jpg', 'position' => 1],
                    ['src' => 'http://local/image2.jpg', 'position' => 2],
                ],
            ],
        ]);

        $this->dataServiceMock->shouldReceive('getOrderedImages')->once()->andReturn([
            ['path' => 'image1.jpg', 'src' => 'http://local/image1.jpg'],
            ['path' => 'image2.jpg', 'src' => 'http://local/image2.jpg'],
        ]);

        $this->dataServiceMock->shouldReceive('prepareVariationsData')->once()->andReturn(['create' => [], 'update' => []]);

        // 3. Mock HTTP Requests
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'GET' && str_contains($url, 'products/12345.json') && ! str_contains($url, '/images.json') && ! str_contains($url, '/variants.json')) {
                return Http::response([
                    'product' => [
                        'id' => 12345,
                        'title' => 'Old Title',
                        'body_html' => 'Desc',
                        'images' => [
                            ['id' => 111, 'src' => 'https://cdn.shopify.com/s/files/image1_v1.jpg'],
                        ],
                    ],
                ], 200);
            }

            if ($method === 'PUT' && str_contains($url, 'products/12345.json')) {
                return Http::response(['product' => ['id' => 12345]], 200);
            }

            if ($method === 'GET' && str_contains($url, 'products/12345/images.json')) {
                return Http::response([
                    'images' => [
                        ['id' => 111, 'src' => 'https://cdn.shopify.com/s/files/image1_v1.jpg'],
                    ],
                ], 200);
            }

            if ($method === 'POST' && str_contains($url, 'products/12345/images.json')) {
                return Http::response(['image' => ['id' => 222]], 201);
            }

            if ($method === 'GET' && str_contains($url, 'products/12345/variants.json')) {
                return Http::response(['variants' => []], 200);
            }

            return Http::response([], 200);
        });

        // 4. Run Sync
        $this->connector->syncProduct($storeOverride);

        // 5. Assertions
        Http::assertSent(function ($request) {
            if ($request->method() === 'PUT' && str_contains($request->url(), 'products/12345.json')) {
                return ! isset($request['product']['images'])
                    && $request['product']['title'] === 'New Title';
            }

            return true;
        });

        Http::assertSent(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), 'products/12345/images.json')) {
                return $request['image']['src'] === 'http://local/image2.jpg';
            }

            return true;
        });

        Http::assertNotSent(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), 'products/12345/images.json')) {
                return $request['image']['src'] === 'http://local/image1.jpg';
            }

            return false;
        });
    }
}
