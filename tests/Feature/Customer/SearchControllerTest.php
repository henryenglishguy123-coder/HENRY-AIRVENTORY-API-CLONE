<?php

namespace Tests\Feature\Customer;

use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;
use App\Models\Sales\Order\Address\SalesOrderAddress;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class SearchControllerTest extends TestCase
{
    use CreatesTestTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        if (! Schema::hasTable('sales_order_addresses')) {
            Schema::create('sales_order_addresses', function ($table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('address_type');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('store_channels')) {
            Schema::create('store_channels', function ($table) {
                $table->string('code')->primary();
                $table->string('name');
                $table->string('logo')->nullable();
                $table->string('description')->nullable();
                $table->string('auth_type')->nullable();
                $table->json('required_credentials')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            \DB::table('store_channels')->insert([
                ['code' => 'shopify', 'name' => 'Shopify', 'is_active' => true],
                ['code' => 'woocommerce', 'name' => 'WooCommerce', 'is_active' => true],
            ]);
        }

        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function ($table) {
                $table->id();
                $table->string('currency')->nullable();
                $table->string('code')->nullable();
                $table->string('symbol')->nullable();
                $table->string('localization_code')->nullable();
                $table->float('rate')->default(1);
                $table->boolean('is_allowed')->default(true);
                $table->boolean('is_default')->default(true);
                $table->timestamps();
            });
            \DB::table('currencies')->insert([
                'currency' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'localization_code' => 'en_US',
                'rate' => 1,
                'is_allowed' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/customers/search?q=test');
        $response->assertStatus(401);
        // Structure may vary by middleware, just ensure unauthorized
        $this->assertEquals('Unauthorized', $response->json('message'));
    }

    public function test_orders_search_returns_paginated_results()
    {
        $customer = Vendor::factory()->create();
        $this->actingAs($customer, 'customer');

        // Create orders with matching names
        for ($i = 1; $i <= 5; $i++) {
            $order = SalesOrder::create([
                'order_number' => "ORD-00{$i}",
                'customer_id' => $customer->id,
                'grand_total_inc_margin' => 100.00,
                'grand_total' => 100.00,
                'order_status' => 'confirmed',
                'payment_status' => 'paid',
            ]);
            SalesOrderAddress::create([
                'order_id' => $order->id,
                'address_type' => 'shipping',
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => "alice{$i}@example.com",
            ]);
        }

        $response = $this->getJson('/api/v1/customers/search?q=Alice&type=orders&per_page=2&page=2');
        if ($response->status() !== 200) {
            $this->fail('Response: '.$response->getContent());
        }
        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'query',
                'type',
                'results' => [
                    'orders' => [
                        'total',
                        'items',
                        'pagination' => [
                            'total',
                            'count',
                            'per_page',
                            'current_page',
                            'total_pages',
                        ],
                        'hasMore',
                    ],
                ],
            ],
        ]);

        $this->assertEquals(5, $response->json('data.results.orders.total'));
        $this->assertEquals(2, $response->json('data.results.orders.pagination.per_page'));
        $this->assertEquals(2, $response->json('data.results.orders.pagination.current_page'));
    }

    public function test_store_search_can_filter_by_platform()
    {
        $customer = Vendor::factory()->create();
        $this->actingAs($customer, 'customer');

        VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'shopify',
            'store_identifier' => 'myshop.myshopify.com',
            'status' => 'connected',
        ]);
        VendorConnectedStore::create([
            'vendor_id' => $customer->id,
            'channel' => 'woocommerce',
            'store_identifier' => 'woo.example.com',
            'status' => 'connected',
        ]);

        $response = $this->getJson('/api/v1/customers/search?q=shop&type=stores&platform=shopify');
        if ($response->status() !== 200) {
            $this->fail('Response: '.$response->getContent());
        }
        $response->assertStatus(200);
        $stores = $response->json('data.results.stores.items');
        $this->assertNotEmpty($stores);
        foreach ($stores as $store) {
            $this->assertEquals('shopify', $store['channel']);
        }
    }
}
