<?php

namespace Tests\Feature\Admin\Sales\Order;

use App\Models\Admin\User;
use App\Models\Customer\Vendor;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create printing_techniques table
        if (! \Illuminate\Support\Facades\Schema::hasTable('printing_techniques')) {
            \Illuminate\Support\Facades\Schema::create('printing_techniques', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Base schema is now handled by consolidate migrations
        $this->artisan('migrate');

        // Create users table
        if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('username')->unique();
                $table->string('password');
                $table->string('mobile')->nullable();
                $table->string('user_type')->default('customer');
                $table->boolean('is_blocked')->default(0);
                $table->boolean('is_active')->default(1);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create sales_order_sources table
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_sources')) {
            \Illuminate\Support\Facades\Schema::create('sales_order_sources', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('platform')->default('manual');
                $table->string('source')->default('manual');
                $table->string('source_order_id')->nullable();
                $table->string('source_order_number')->nullable();
                $table->timestamp('source_created_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        // Create vendors table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('mobile')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->boolean('account_status')->default(1);
                $table->string('source')->default('web');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create vendor_wallets table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 15, 4)->default(0);
                $table->timestamps();
            });
        }

        // Create factory_users table
        if (! \Illuminate\Support\Facades\Schema::hasTable('factory_users')) {
            \Illuminate\Support\Facades\Schema::create('factory_users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone_number')->nullable();
                $table->string('password');
                $table->string('source')->default('web');
                $table->string('business_code')->nullable();
                $table->boolean('account_status')->default(1);
                $table->boolean('account_verified')->default(0);
                $table->boolean('catalog_status')->default(0);
                $table->string('stripe_account_id')->nullable();
                $table->string('email_verification_code')->nullable();
                $table->timestamp('email_verification_code_expires_at')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create sales_orders table
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_orders')) {
            \Illuminate\Support\Facades\Schema::create('sales_orders', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('factory_id')->nullable();
                $table->unsignedBigInteger('cart_id')->nullable();
                $table->string('order_status')->default('pending');
                $table->string('payment_status')->default('pending');
                $table->decimal('grand_total', 10, 2)->default(0);
                $table->decimal('grand_total_inc_margin', 10, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create sales_order_addresses table
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_addresses')) {
            \Illuminate\Support\Facades\Schema::create('sales_order_addresses', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('address_type'); // billing or shipping
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->timestamps();
            });
        }

        // Create sales_order_items table (accessed in show method)
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_items')) {
            \Illuminate\Support\Facades\Schema::create('sales_order_items', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create sales_order_brandings table
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_brandings')) {
            \Illuminate\Support\Facades\Schema::create('sales_order_brandings', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_item_id')->unique();
                $table->unsignedBigInteger('packaging_label_id')->nullable();
                $table->unsignedBigInteger('hang_tag_id')->nullable();
                $table->unsignedBigInteger('applied_packaging_label_id')->nullable();
                $table->unsignedBigInteger('applied_hang_tag_id')->nullable();
                $table->decimal('packaging_base_price', 15, 4)->default(0);
                $table->decimal('packaging_margin_price', 15, 4)->default(0);
                $table->decimal('packaging_total_price', 15, 4)->storedAs('packaging_base_price + packaging_margin_price');
                $table->decimal('hang_tag_base_price', 15, 4)->default(0);
                $table->decimal('hang_tag_margin_price', 15, 4)->default(0);
                $table->decimal('hang_tag_total_price', 15, 4)->storedAs('hang_tag_base_price + hang_tag_margin_price');
                $table->timestamps();
            });
        }

        // Create sales_order_payments table (accessed in show method)
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_payments')) {
            \Illuminate\Support\Facades\Schema::create('sales_order_payments', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create store_channels table
        if (! \Illuminate\Support\Facades\Schema::hasTable('store_channels')) {
            \Illuminate\Support\Facades\Schema::create('store_channels', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('code');
                $table->string('name');
                $table->string('logo')->nullable();
                $table->string('auth_type')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamps();
            });
        }

        // Create stores table
        if (! \Illuminate\Support\Facades\Schema::hasTable('stores')) {
            \Illuminate\Support\Facades\Schema::create('stores', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('store_name');
                $table->string('icon')->nullable();
                $table->string('favicon')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create currencies table
        // Create printing_techniques table
        if (! \Illuminate\Support\Facades\Schema::hasTable('printing_techniques')) {
            \Illuminate\Support\Facades\Schema::create('printing_techniques', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('currencies')) {
            \Illuminate\Support\Facades\Schema::create('currencies', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('currency');
                $table->string('code')->unique();
                $table->string('symbol');
                $table->string('localization_code')->nullable();
                $table->decimal('rate', 15, 8)->default(1);
                $table->boolean('is_allowed')->default(0);
                $table->boolean('is_default')->default(0);
                $table->timestamps();
            });

            // Seed default currency
            if (\App\Models\Currency\Currency::count() === 0) {
                \App\Models\Currency\Currency::create([
                    'currency' => 'US Dollar',
                    'code' => 'USD',
                    'symbol' => '$',
                    'localization_code' => 'en_US',
                    'rate' => 1.00000000,
                    'is_allowed' => 1,
                    'is_default' => 1,
                ]);
            }
        }
    }

    protected function createTestAdmin()
    {
        return User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'username' => 'testadmin',
            'password' => Hash::make('password123'),
            'mobile' => '1234567890',
            'user_type' => 'admin',
            'is_blocked' => 0,
            'is_active' => 1,
        ]);
    }

    protected function createOrder($overrides = [])
    {
        // Need a customer first
        // Check if Vendor factory works, otherwise create manually
        try {
            $customer = Vendor::factory()->create();
        } catch (\Throwable $e) {
            // Fallback if factory fails or not found
            $customer = Vendor::create([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test'.uniqid().'@example.com',
                'password' => Hash::make('password'),
                'mobile' => '1234567890',
            ]);
        }

        return SalesOrder::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $customer->id,
            'factory_id' => 1,
            'cart_id' => 1,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'grand_total' => 100.00,
            'grand_total_inc_margin' => 120.00,
        ], $overrides));
    }

    public function test_admin_can_list_orders()
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        for ($i = 0; $i < 15; $i++) {
            $this->createOrder();
        }

        // Use new pagination params
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/orders?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'order_status',
                        'payment_status',
                        'grand_total_inc_margin',
                        'created_at',
                    ],
                ],
                'pagination' => [
                    'total',
                    'count',
                    'per_page',
                    'current_page',
                    'total_pages',
                ],
            ]);

        $this->assertEquals(15, $response->json('pagination.total'));
        $this->assertCount(10, $response->json('data'));
    }

    public function test_admin_can_view_order_details()
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        $order = $this->createOrder();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/admin/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);
    }

    public function test_admin_can_filter_orders()
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        // Create orders with specific attributes
        $completedOrder = $this->createOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);
        $completedOrder->created_at = now()->subDays(5);
        $completedOrder->save();

        $pendingOrder = $this->createOrder([
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $pendingOrder->created_at = now();
        $pendingOrder->save();

        $cancelledOrder = $this->createOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'failed',
        ]);
        $cancelledOrder->created_at = now()->subDays(10);
        $cancelledOrder->save();

        // Filter by status 'delivered'
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/orders?status=delivered');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($completedOrder->id, $data[0]['id']);

        // Filter by payment_status 'pending'
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/orders?payment_status=pending');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($pendingOrder->id, $data[0]['id']);

        // Filter by date range (last 7 days - should include completed and pending, exclude cancelled)
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/admin/orders?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // pending (today) and completed (5 days ago) are in range. cancelled (10 days ago) is out.
        $this->assertCount(2, $data);
        $ids = collect($data)->pluck('id')->toArray();
        $this->assertContains($completedOrder->id, $ids);
        $this->assertContains($pendingOrder->id, $ids);
        $this->assertNotContains($cancelledOrder->id, $ids);
    }

    public function test_order_response_includes_source_info()
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        // Setup default store with icon
        \App\Models\Admin\Store\Store::create([
            'store_name' => 'Test Store',
            'icon' => 'settings/logo.png',
            'favicon' => 'settings/favicon.png',
        ]);

        // Setup Shopify channel
        \App\Models\StoreChannels\StoreChannel::create([
            'code' => 'shopify',
            'name' => 'Shopify',
            'logo' => 'channels/shopify.svg',
            'auth_type' => 'oauth',
            'is_active' => 1,
        ]);

        try {
            $vendor = Vendor::factory()->create();
        } catch (\Throwable $e) {
            // Fallback if factory fails or doesn't exist
            $vendor = Vendor::create([
                'first_name' => 'Test',
                'last_name' => 'Vendor',
                'email' => 'test@vendor.com',
                'password' => bcrypt('password'),
                // Add other required fields if any
            ]);
        }

        $order = SalesOrder::create([
            'order_number' => 'ORD-TEST-SOURCE',
            'customer_id' => $vendor->id,
            'grand_total_inc_margin' => 100.00,
        ]);

        // 1. Test default (no source) - Should fallback to Store icon
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson(route('admin.orders.show', $order->id));

        $response->assertOk()
            ->assertJsonPath('data.source.platform', 'airventory')
            // Note: getImageUrl mocks Storage/Cache so we might get fallback or generated URL.
            // Since we didn't mock Storage, it returns fallback or s3 url.
            // But checking that it returns something is good.
            ->assertJsonPath('data.source.logo_url', function ($url) {
                return ! empty($url);
            });

        // 2. Test with Shopify source
        \App\Models\Sales\Order\SalesOrderSource::create([
            'order_id' => $order->id,
            'platform' => 'shopify',
            'source_order_number' => '#1001',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson(route('admin.orders.show', $order->id));

        $response->assertOk()
            ->assertJsonPath('data.source.platform', 'shopify')
            ->assertJsonPath('data.source.source_order_number', '#1001')
            ->assertJsonPath('data.source.logo_url', function ($url) {
                return ! empty($url);
            });

        // Check list endpoint too
        $responseList = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson(route('admin.orders.index'));

        $responseList->assertOk();

        // Assert that at least one order has the shopify platform
        $data = $responseList->json('data');
        $hasShopify = collect($data)->contains(function ($item) {
            return ($item['source']['platform'] ?? null) === 'shopify';
        });

        $this->assertTrue($hasShopify, 'Order list should contain an order with shopify source platform');
    }
}
