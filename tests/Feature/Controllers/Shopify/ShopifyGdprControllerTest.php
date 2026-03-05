<?php

namespace Tests\Feature\Controllers\Shopify;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ShopifyGdprControllerTest extends TestCase
{
    protected string $secret = 'test_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.shopify.secret', $this->secret);
    }

    public function test_customers_data_request_validates_signature_and_logs_safe_metadata()
    {
        $payload = ['shop_id' => 123, 'shop_domain' => 'test.myshopify.com', 'customer' => ['email' => 'pii@example.com']];
        $jsonPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $jsonPayload, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'Content-Type' => 'application/json',
        ];

        Log::shouldReceive('info')
            ->with('Shopify GDPR Request Received: customers/data_request', \Mockery::on(function ($context) {
                return $context['shop_domain'] === 'test.myshopify.com'
                    && $context['shop_id'] === 123
                    && ! isset($context['payload']); // Ensure full payload (PII) is NOT logged
            }))
            ->once();

        $response = $this->call(
            'POST',
            route('shopify.gdpr.customers.data_request'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $response->assertStatus(200);
    }

    public function test_customers_redact_validates_signature_and_logs_safe_metadata()
    {
        $payload = ['shop_id' => 123, 'shop_domain' => 'test.myshopify.com', 'customer' => ['email' => 'pii@example.com']];
        $jsonPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $jsonPayload, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'Content-Type' => 'application/json',
        ];

        Log::shouldReceive('info')
            ->with('Shopify GDPR Request Received: customers/redact', \Mockery::on(function ($context) {
                return $context['shop_domain'] === 'test.myshopify.com'
                    && $context['shop_id'] === 123
                    && ! isset($context['payload']);
            }))
            ->once();

        $response = $this->call(
            'POST',
            route('shopify.gdpr.customers.redact'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $response->assertStatus(200);
    }

    public function test_shop_redact_validates_signature_and_logs_safe_metadata()
    {
        $payload = ['shop_id' => 123, 'shop_domain' => 'test.myshopify.com'];
        $jsonPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $jsonPayload, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'Content-Type' => 'application/json',
        ];

        Log::shouldReceive('info')
            ->with('Shopify GDPR Request Received: shop/redact', \Mockery::on(function ($context) {
                return $context['shop_domain'] === 'test.myshopify.com'
                    && $context['shop_id'] === 123
                    && ! isset($context['payload']);
            }))
            ->once();

        $response = $this->call(
            'POST',
            route('shopify.gdpr.shop.redact'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $response->assertStatus(200);
    }

    public function test_gdpr_request_rejects_invalid_signature()
    {
        $payload = ['shop_id' => 123];
        $headers = [
            'X-Shopify-Hmac-Sha256' => 'invalid_signature',
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
        ];

        $response = $this->postJson(route('shopify.gdpr.customers.data_request'), $payload, $headers);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_gdpr_request_rejects_missing_signature()
    {
        $response = $this->postJson(route('shopify.gdpr.customers.data_request'), []);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }
}
