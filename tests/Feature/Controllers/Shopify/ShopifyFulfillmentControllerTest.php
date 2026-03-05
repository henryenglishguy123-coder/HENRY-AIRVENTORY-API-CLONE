<?php

namespace Tests\Feature\Controllers\Shopify;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ShopifyFulfillmentControllerTest extends TestCase
{
    protected string $secret = 'test_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.shopify.secret', $this->secret);
    }

    public function test_callback_validates_signature_with_canonical_header()
    {
        $payload = ['kind' => 'fulfillment_request'];
        $content = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $content, $this->secret, true));

        // Use the canonical header casing we just standardized on
        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.fulfillment.callback'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $content
        );

        $response->assertStatus(200);
    }

    public function test_callback_notification_route_works()
    {
        $payload = ['kind' => 'fulfillment_request'];
        $content = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $content, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.fulfillment.callback.notification'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $content
        );

        $response->assertStatus(200);
    }

    public function test_callback_validates_payload_structure()
    {
        $payload = ['foo' => 'bar']; // Missing 'kind'
        $content = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $content, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.fulfillment.callback'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $content
        );

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid payload']);
    }

    public function test_callback_rejects_invalid_signature()
    {
        $payload = ['foo' => 'bar'];
        $content = json_encode($payload);
        $hmac = 'invalid_hmac';

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.fulfillment.callback'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $content
        );

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_callback_rejects_missing_signature()
    {
        $payload = ['foo' => 'bar'];
        $content = json_encode($payload);

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.fulfillment.callback'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $content
        );

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }
}
