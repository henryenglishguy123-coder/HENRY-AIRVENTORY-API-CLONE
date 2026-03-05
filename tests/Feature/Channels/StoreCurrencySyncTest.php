<?php

namespace Tests\Feature\Channels;

use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class StoreCurrencySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_woocommerce_fetches_currency_on_normalize()
    {
        $channel = StoreChannel::create([
            'code' => 'woocommerce',
            'name' => 'WooCommerce',
            'auth_type' => 'basic_auth',
        ]);
        $dataService = Mockery::mock(WooCommerceDataService::class);
        $connector = new WooCommerceConnector($channel, $dataService);

        $payload = [
            'store_url' => 'https://woo-store.com',
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ];

        Http::fake([
            'https://woo-store.com/wp-json/wc/v3/settings/general/woocommerce_currency' => Http::response([
                'value' => 'USD',
            ], 200),
        ]);

        $normalized = $connector->normalizeInstallPayload($payload);

        $this->assertEquals('USD', $normalized['currency']);
    }

    public function test_shopify_fetches_currency_on_normalize()
    {
        $channel = StoreChannel::create([
            'code' => 'shopify',
            'name' => 'Shopify',
            'auth_type' => 'oauth',
        ]);
        $connector = new ShopifyConnector($channel);

        // Inject access token via reflection or by mocking the flow that sets it.
        // Since accessToken is private, we can use reflection to set it for testing.
        $reflection = new \ReflectionClass($connector);
        $property = $reflection->getProperty('accessToken');
        $property->setAccessible(true);
        $property->setValue($connector, 'shpat_token');

        $shop = 'test-shop.myshopify.com';
        $payload = ['shop' => $shop];

        Config::set('services.shopify.api_version', '2024-01');

        Http::fake([
            "https://{$shop}/admin/api/2024-01/shop.json" => Http::response([
                'shop' => [
                    'currency' => 'EUR',
                ],
            ], 200),
        ]);

        $normalized = $connector->normalizeInstallPayload($payload);

        $this->assertEquals('EUR', $normalized['currency']);
    }
}
