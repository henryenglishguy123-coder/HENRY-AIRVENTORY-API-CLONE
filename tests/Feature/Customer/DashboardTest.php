<?php

namespace Tests\Feature\Customer;

use App\Models\Customer\Vendor;
use App\Models\Sales\Order\Address\SalesOrderAddress;
use App\Models\Sales\Order\SalesOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create vendors table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function ($table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('mobile')->nullable();
                $table->string('password');
                $table->integer('account_status')->default(1);
                $table->string('source')->default('web');
                $table->string('social_login_id')->nullable();
                $table->string('gateway_customer_id')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create sales_orders table
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_orders')) {
            \Illuminate\Support\Facades\Schema::create('sales_orders', function ($table) {
                $table->id();
                $table->string('order_number');
                $table->unsignedBigInteger('customer_id');
                $table->decimal('grand_total_inc_margin', 10, 2);
                $table->decimal('grand_total', 10, 2);
                $table->string('order_status');
                $table->string('payment_status')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create vendor_wallets table
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 10, 4)->default(0);
                $table->timestamps();
            });
        }

        // Create sales_order_addresses table
        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_order_addresses')) {
            \Illuminate\Support\Facades\Schema::create('sales_order_addresses', function ($table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('address_type');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function createCustomer()
    {
        return Vendor::factory()->create();
    }

    public function test_dashboard_stats_and_recent_orders()
    {
        $customer = $this->createCustomer();
        $this->actingAs($customer, 'customer');

        // Create Orders

        // Order 1: Today (Revenue 100, Profit 20)
        $order1 = SalesOrder::create([
            'order_number' => 'ORD-001',
            'customer_id' => $customer->id,
            'grand_total_inc_margin' => 100.00,
            'grand_total' => 80.00,
            'created_at' => Carbon::now(),
            'order_status' => 'processing',
            'payment_status' => 'paid',
        ]);
        SalesOrderAddress::create([
            'order_id' => $order1->id,
            'address_type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Order 2: Yesterday (Revenue 50, Profit 10)
        $order2 = SalesOrder::create([
            'order_number' => 'ORD-002',
            'customer_id' => $customer->id,
            'grand_total_inc_margin' => 50.00,
            'grand_total' => 40.00,
            'order_status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $order2->created_at = Carbon::now()->subDay();
        $order2->save();

        // Order 3: 40 Days ago (Revenue 200, Profit 50) - Should be excluded from 30_days stats
        $order3 = SalesOrder::create([
            'order_number' => 'ORD-003',
            'customer_id' => $customer->id,
            'grand_total_inc_margin' => 200.00,
            'grand_total' => 150.00,
            'order_status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $order3->created_at = Carbon::now()->subDays(40);
        $order3->save();

        // Test 7 Days Period
        $response = $this->getJson(route('customer.dashboard', ['period' => '7_days']));

        $response->assertStatus(200);

        // Assert Stats
        // 2 orders in last 7 days (ORD-001, ORD-002)
        $response->assertJsonPath('stats.total_orders', 2);
        $response->assertJsonPath('stats.total_revenue', 150); // 100 + 50
        $response->assertJsonPath('stats.total_profit', 30); // 20 + 10

        // Test 30 Days Period
        $response30 = $this->getJson(route('customer.dashboard', ['period' => '30_days']));

        $response30->assertJsonPath('stats.total_orders', 2); // Still 2, as ORD-003 is 40 days ago

        // Test Custom Period including 40 days ago
        $customStart = Carbon::now()->subDays(50)->toDateString();
        $customEnd = Carbon::now()->toDateString();
        $responseCustom = $this->getJson(route('customer.dashboard', [
            'period' => 'custom',
            'start_date' => $customStart,
            'end_date' => $customEnd,
        ]));

        $responseCustom->assertJsonPath('stats.total_orders', 3);
        $responseCustom->assertJsonPath('stats.total_revenue', 350); // 150 + 200

        // Check Graph Data Structure
        $graphData = $response->json('graph_data');
        $this->assertNotEmpty($graphData);
        $this->assertArrayHasKey('date', $graphData[0]);
        // Verify date format (e.g. 04 Feb 2026)
        $this->assertMatchesRegularExpression('/^\d{2} [A-Z][a-z]{2} \d{4}$/', $graphData[0]['date']);
        $this->assertArrayHasKey('orders_count', $graphData[0]);
        $this->assertArrayNotHasKey('revenue', $graphData[0]);
        $this->assertArrayNotHasKey('profit', $graphData[0]);

        // Check Recent Orders Structure
        $recentOrders = $response->json('recent_orders');
        $this->assertCount(3, $recentOrders); // Recent orders ignores date filter, returns all latest 5
        $this->assertEquals('ORD-001', $recentOrders[0]['order_number']);
        $this->assertEquals('John Doe', $recentOrders[0]['recipient_name']);
        // Verify recent order date format (e.g. 04 Feb 2026 12:00 PM)
        $this->assertMatchesRegularExpression('/^\d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2} [AP]M$/', $recentOrders[0]['order_date']);
        $this->assertEquals('paid', $recentOrders[0]['payment_status']);
    }
}
