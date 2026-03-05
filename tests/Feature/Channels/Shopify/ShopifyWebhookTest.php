<?php

namespace Tests\Feature\Channels\Shopify;

use App\Jobs\Shopify\ProcessShopifyOrderJob;
use App\Jobs\Shopify\ProcessShopifyUninstallJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.shopify.secret', $this->secret);
        Queue::fake();
    }

    public function test_webhook_logs_error_when_secret_missing()
    {
        Config::set('services.shopify.secret', null);

        Log::shouldReceive('error')
            ->with('Shopify secret is not configured (services.shopify.secret).')
            ->once();

        Log::shouldReceive('warning')
            ->with('Shopify webhook signature verification failed', \Mockery::any())
            ->once();

        $this->postJson(route('shopify.webhooks.orders'), []);
    }

    public function test_webhook_rejects_missing_signature()
    {
        $response = $this->postJson(route('shopify.webhooks.orders'), []);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid signature']);
    }

    public function test_webhook_rejects_invalid_signature()
    {
        $payload = ['id' => 12345];
        $headers = [
            'X-Shopify-Hmac-Sha256' => 'invalid_signature',
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'X-Shopify-Topic' => 'orders/updated',
        ];

        $response = $this->postJson(route('shopify.webhooks.orders'), $payload, $headers);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid signature']);
    }

    public function test_orders_webhook_accepts_valid_signature_and_dispatches_job()
    {
        $payload = ['id' => 12345, 'note' => 'test order'];
        $jsonPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $jsonPayload, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'X-Shopify-Topic' => 'orders/create',
            'Content-Type' => 'application/json',
        ];

        // We use call() directly or ensure postJson works as expected.
        // postJson encodes the data, which is fine as long as the controller reads the body correctly.
        $response = $this->call(
            'POST',
            route('shopify.webhooks.orders'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        Queue::assertPushed(ProcessShopifyOrderJob::class, function ($job) {
            return $job->shopDomain === 'test.myshopify.com' &&
                   $job->payload['id'] === 12345;
        });
    }

    public function test_orders_webhook_ignores_non_create_topic()
    {
        $payload = ['id' => 12345, 'note' => 'test order'];
        $jsonPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $jsonPayload, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'X-Shopify-Topic' => 'orders/updated',
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.webhooks.orders'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        Queue::assertNotPushed(ProcessShopifyOrderJob::class);
    }

    public function test_uninstall_webhook_accepts_valid_signature_and_dispatches_job()
    {
        $payload = ['id' => 12345];
        $jsonPayload = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $jsonPayload, $this->secret, true));

        $headers = [
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Shop-Domain' => 'test.myshopify.com',
            'X-Shopify-Topic' => 'app/uninstalled',
            'Content-Type' => 'application/json',
        ];

        $response = $this->call(
            'POST',
            route('shopify.webhooks.uninstall'),
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        Queue::assertPushed(ProcessShopifyUninstallJob::class, function ($job) {
            return $job->shopDomain === 'test.myshopify.com';
        });
    }
}
