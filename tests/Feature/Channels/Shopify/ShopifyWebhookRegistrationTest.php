<?php

namespace Tests\Feature\Channels\Shopify;

use App\Services\Channels\Shopify\ShopifyWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ShopifyWebhookRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected ShopifyWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopifyWebhookService;
        Config::set('services.shopify.api_version', '2024-01');
    }

    public function test_register_handles_already_taken_error_gracefully()
    {
        Http::fake([
            'https://test.myshopify.com/admin/api/2024-01/webhooks.json' => Http::response([
                'errors' => ['address' => ['for this topic has already been taken']],
            ], 422),
        ]);

        Log::shouldReceive('info')
            ->with('Shopify webhook already exists', \Mockery::any())
            ->times(4); // 4 webhooks

        $this->service->register('test.myshopify.com', 'test_token');
    }

    public function test_register_throws_exception_for_other_validation_errors()
    {
        Http::fake([
            'https://test.myshopify.com/admin/api/2024-01/webhooks.json' => function ($request) {
                $data = $request->data();
                // Let orders/create pass to verify exception on other topics
                if ($data['webhook']['topic'] === 'orders/create') {
                    return Http::response([], 200);
                }

                return Http::response([
                    'errors' => ['format' => ['must be json']],
                ], 422);
            },
        ]);

        Log::shouldReceive('info')
            ->with('Shopify webhook registered', \Mockery::any())
            ->once(); // for orders/create

        Log::shouldReceive('error')
            ->with('Shopify webhook validation failed', \Mockery::any())
            ->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to register webhook orders/updated: Validation error');

        $this->service->register('test.myshopify.com', 'test_token');
    }

    public function test_register_logs_warning_and_continues_for_orders_create_failure()
    {
        Http::fake([
            'https://test.myshopify.com/admin/api/2024-01/webhooks.json' => function ($request) {
                $data = $request->data();
                if ($data['webhook']['topic'] === 'orders/create') {
                    return Http::response(['errors' => ['topic' => ['unauthorized']]], 422);
                }

                return Http::response([], 200);
            },
        ]);

        Log::shouldReceive('warning')
            ->with(\Mockery::pattern('/Failed to register optional webhook orders\/create/'))
            ->once();

        Log::shouldReceive('info')
            ->with('Shopify webhook registered', \Mockery::any())
            ->times(3); // The other 3 webhooks

        $this->service->register('test.myshopify.com', 'test_token');
    }
}
