<?php

namespace Tests\Feature\Customer\Store;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Contracts\StoreConnectorInterface;
use App\Services\Channels\Factory\StoreConnectorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class ProductLookupTest extends TestCase
{
    use CreatesTestTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();
        $this->beforeApplicationDestroyed(function () {
            if (Schema::hasTable('vendor_wallets')) {
                Schema::drop('vendor_wallets');
            }
            if (Schema::hasTable('vendor_metas')) {
                Schema::drop('vendor_metas');
            }
        });
        StoreChannel::create(['code' => 'shopify', 'name' => 'Shopify', 'auth_type' => 'oauth']);
        StoreChannel::create(['code' => 'woocommerce', 'name' => 'WooCommerce', 'auth_type' => 'api_key']);
    }

    public function test_product_lookup_success_shopify(): void
    {
        $customer = Vendor::factory()->create();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'shopify',
            'store_identifier' => 'shop-123',
            'link' => 'https://shop.example',
            'token' => encrypt('shpat_abc'),
            'status' => 'connected',
        ]);

        $mockConnector = $this->mock(StoreConnectorInterface::class);
        $mockConnector->shouldReceive('getProductByExternalId')
            ->once()
            ->withArgs(fn ($s, $id) => $s->id === $store->id && $id === 'gid://shopify/Product/123')
            ->andReturn([
                'id' => 123,
                'title' => 'Sample Product',
                'handle' => 'sample-product',
                'variants' => [
                    ['id' => 1, 'title' => 'Default Title', 'sku' => 'SKU-1'],
                ],
                'options' => [
                    ['name' => 'Size', 'values' => ['S', 'M']],
                ],
                'image' => ['id' => 9, 'src' => 'https://image.example/9.jpg'],
            ]);

        $this->mock(StoreConnectorFactory::class)
            ->shouldReceive('make')
            ->once()
            ->andReturn($mockConnector);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('customer.stores.product-lookup', [
                'product_id' => 'gid://shopify/Product/123',
                'store_id' => $store->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'store' => ['id', 'channel', 'domain'],
                    'product' => ['external_product_id', 'title', 'description', 'primary_image', 'options', 'variants'],
                ],
            ])
            ->assertJsonPath('data.product.external_product_id', 123);
    }

    public function test_product_lookup_not_found_returns_200_with_flag(): void
    {
        $customer = Vendor::factory()->create();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'woocommerce',
            'store_identifier' => 'woo-abc',
            'link' => 'https://woo.example',
            'token' => encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']),
            'status' => 'connected',
        ]);

        $mockConnector = $this->mock(StoreConnectorInterface::class);
        $mockConnector->shouldReceive('getProductByExternalId')
            ->once()
            ->andReturn(null);

        $this->mock(StoreConnectorFactory::class)
            ->shouldReceive('make')
            ->once()
            ->andReturn($mockConnector);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('customer.stores.product-lookup', [
                'product_id' => '123',
                'store_id' => $store->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Product not found for this store.');
    }

    public function test_product_lookup_store_ownership_enforced(): void
    {
        $customer = Vendor::factory()->create();
        $other = Vendor::factory()->create();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $other->id,
            'channel' => 'shopify',
            'store_identifier' => 'shop-xyz',
            'link' => 'https://shop.other',
            'token' => encrypt('shpat_abc'),
            'status' => 'connected',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('customer.stores.product-lookup', [
                'product_id' => 'gid://shopify/Product/123',
                'store_id' => $store->id,
            ]));

        $response->assertStatus(404);
    }

    public function test_product_lookup_duplicate_sync_returns_409(): void
    {
        $customer = Vendor::factory()->create();
        $token = auth('customer')->login($customer);

        $store = VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'woocommerce',
            'store_identifier' => 'woo-dup',
            'link' => 'https://woo.dup',
            'token' => encrypt(['consumer_key' => 'ck', 'consumer_secret' => 'cs']),
            'status' => 'connected',
        ]);

        $cdt = CatalogDesignTemplate::create(['name' => 'Test', 'status' => 1]);
        $template = VendorDesignTemplate::create([
            'vendor_id' => $customer->id,
            'catalog_design_template_id' => $cdt->id,
        ]);

        $override = VendorDesignTemplateStore::create([
            'vendor_id' => $customer->id,
            'vendor_design_template_id' => $template->id,
            'vendor_connected_store_id' => $store->id,
            'external_product_id' => '123',
            'status' => 'active',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('customer.stores.product-lookup', [
                'product_id' => '123',
                'store_id' => $store->id,
            ]));

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.store_override_id', $override->id)
            ->assertJsonPath('data.vendor_design_template_id', $template->id);
    }
}
