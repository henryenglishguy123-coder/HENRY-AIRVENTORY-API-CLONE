<?php

namespace Tests\Feature\Channels\Shopify;

use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Shopify\ShopifyConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected ShopifyConnector $connector;

    protected string $secret = 'test_secret_key';

    protected string $apiKey = 'test_api_key';

    protected function setUp(): void
    {
        parent::setUp();

        $channel = StoreChannel::create(['code' => 'shopify', 'name' => 'Shopify', 'auth_type' => 'oauth']);
        $this->connector = new ShopifyConnector($channel);

        Config::set('services.shopify.key', $this->apiKey);
        Config::set('services.shopify.secret', $this->secret);
        Config::set('services.shopify.scopes', 'read_orders,write_products');
        Config::set('services.shopify.api_version', '2024-01');
    }

    public function test_build_authorize_url_generates_correct_url()
    {
        $vendorId = 1;
        $storeUrl = 'my-shop.myshopify.com';
        $nonce = 'test_nonce';

        $url = $this->connector->buildAuthorizeUrl($vendorId, $storeUrl, $nonce);

        $this->assertStringStartsWith('https://my-shop.myshopify.com/admin/oauth/authorize', $url);
        $this->assertStringContainsString("client_id={$this->apiKey}", $url);
        $this->assertStringContainsString('scope=read_orders%2Cwrite_products', $url); // Comma encoded
        $this->assertStringContainsString("state={$nonce}", $url);
    }

    public function test_build_authorize_url_throws_exception_missing_config()
    {
        Config::set('services.shopify.key', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify API key is not configured');

        $this->connector->buildAuthorizeUrl(1, 'shop.myshopify.com', 'nonce');
    }

    public function test_validate_install_callback_valid_hmac_and_state()
    {
        $shop = 'test-shop.myshopify.com';
        $code = 'auth_code_123';
        $nonce = 'nonce_123';
        $timestamp = time();

        // 1. Setup Cache for state verification
        Cache::put("store_oauth_pending:{$nonce}", ['vendor_id' => 123], 600);

        // 2. Prepare params for HMAC
        $params = [
            'shop' => $shop,
            'code' => $code,
            'state' => $nonce,
            'timestamp' => $timestamp,
        ];

        ksort($params);
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $hmac = hash_hmac('sha256', $queryString, $this->secret);

        // 3. Mock Token Exchange
        Http::fake([
            "https://{$shop}/admin/oauth/access_token" => Http::response([
                'access_token' => 'shpat_access_token',
                'scope' => 'read_orders',
                'associated_user' => ['id' => 1, 'email' => 'test@example.com'],
            ], 200),
        ]);

        // 4. Create Request
        $request = Request::create('/callback', 'GET', array_merge($params, ['hmac' => $hmac]));

        // 5. Execute
        $vendorId = $this->connector->validateInstallCallback($request);

        // 6. Assertions
        $this->assertEquals(123, $vendorId);

        // Check if token was exchanged correctly
        Http::assertSent(function ($request) use ($shop) {
            return $request->url() === "https://{$shop}/admin/oauth/access_token" &&
                   $request['code'] === 'auth_code_123';
        });

        // Verify payload normalization works after successful validation
        $payload = $this->connector->normalizeInstallPayload(['shop' => $shop]);
        $this->assertEquals($shop, $payload['store_identifier']);
        $this->assertArrayHasKey('associated_user', $payload['additional_data']);
    }

    public function test_validate_install_callback_invalid_hmac()
    {
        $nonce = 'nonce_123';
        Cache::put("store_oauth_pending:{$nonce}", ['vendor_id' => 123], 600);

        $params = [
            'shop' => 'test.myshopify.com',
            'code' => '123',
            'state' => $nonce,
            'hmac' => 'invalid_hmac',
        ];

        $request = Request::create('/callback', 'GET', $params);

        $result = $this->connector->validateInstallCallback($request);

        $this->assertNull($result);
    }

    public function test_validate_install_callback_invalid_shop_domain()
    {
        $nonce = 'nonce_123';
        Cache::put("store_oauth_pending:{$nonce}", ['vendor_id' => 123], 600);

        $shop = '-invalid-start.myshopify.com';
        $params = [
            'shop' => $shop,
            'code' => '123',
            'state' => $nonce,
        ];

        // Calculate HMAC for the invalid shop request so we pass HMAC check and hit domain check
        ksort($params);
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $hmac = hash_hmac('sha256', $queryString, $this->secret);

        $request = Request::create('/callback', 'GET', array_merge($params, ['hmac' => $hmac]));

        $result = $this->connector->validateInstallCallback($request);

        $this->assertNull($result);
    }

    public function test_verify_method_success()
    {
        $shop = 'test.myshopify.com';
        $token = 'shpat_123';

        Http::fake([
            "https://{$shop}/admin/api/2024-01/shop.json" => Http::response(['shop' => ['id' => 1]], 200),
        ]);

        $credentials = [
            'shop' => $shop,
            'access_token' => $token,
        ];

        $result = $this->connector->verify($credentials);

        $this->assertTrue($result);
    }

    public function test_verify_method_failure()
    {
        $shop = 'test.myshopify.com';

        Http::fake([
            "https://{$shop}/admin/api/2024-01/shop.json" => Http::response([], 401),
        ]);

        $credentials = [
            'shop' => $shop,
            'access_token' => 'invalid_token',
        ];

        $result = $this->connector->verify($credentials);

        $this->assertFalse($result);
    }
}
