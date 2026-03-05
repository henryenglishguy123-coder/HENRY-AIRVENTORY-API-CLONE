<?php

namespace Tests\Feature\Services\Sales\Order;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartItem;
use App\Models\Customer\Cart\CartSource;
use App\Models\Location\Country;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\SalesOrderSource;
use App\Services\Sales\Order\CartToOrderService;
use App\Services\Sales\Order\OrderDiscountService;
use App\Services\Tax\TaxResolverService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestTables;

class CartToOrderServiceSourceTest extends TestCase
{
    use CreatesTestTables, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();

        // Ensure US country exists
        Country::firstOrCreate(
            ['iso2' => 'US'],
            ['name' => 'United States', 'id' => 1]
        );

        // Ensure Factory Shipping Rate exists
        if (! DB::table('factory_shipping_rates')->where('factory_id', 1)->exists()) {
            DB::table('factory_shipping_rates')->insert([
                'factory_id' => 1,
                'country_code' => 'US',
                'rate' => 5.00,
            ]);
        }
    }

    public function test_convert_transfers_source_created_at_to_sales_order_source()
    {
        // 1. Setup Cart
        $cart = Cart::create([
            'vendor_id' => 1,
            'status' => 'active',
        ]);

        // 2. Setup Cart Source with created_at
        $sourceCreatedAt = '2023-10-27 10:00:00';
        CartSource::create([
            'cart_id' => $cart->id,
            'platform' => 'shopify',
            'source' => 'test-shop.myshopify.com',
            'source_order_id' => '12345',
            'source_order_number' => '1001',
            'source_created_at' => $sourceCreatedAt,
            'payload' => ['some' => 'data'],
        ]);

        // 3. Setup Cart Item (Required for conversion)
        $product = CatalogProduct::factory()->create(['name' => 'Test Product']);
        $variant = CatalogProduct::factory()->create(['type' => 'variant', 'sku' => 'TEST-SKU']);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => 'TEST-SKU',
            'product_title' => 'Test Product',
            'qty' => 1,
            'unit_price' => 10.00,
            'line_total' => 10.00,
            'fulfillment_factory_id' => 1,
        ]);

        // 4. Mock Services
        $taxResolver = $this->mock(TaxResolverService::class);
        $taxResolver->shouldReceive('resolveTaxRate')->andReturn(0);

        $discountService = $this->mock(OrderDiscountService::class);

        // 5. Run Conversion
        $service = new CartToOrderService($taxResolver, $discountService);
        $orders = $service->convert($cart, 'shopify');

        // 6. Assertions
        $this->assertCount(1, $orders);
        $order = $orders->first();

        $this->assertInstanceOf(SalesOrder::class, $order);

        $salesOrderSource = SalesOrderSource::where('order_id', $order->id)->first();
        $this->assertNotNull($salesOrderSource);
        $this->assertEquals('shopify', $salesOrderSource->platform);
        $this->assertEquals('test-shop.myshopify.com', $salesOrderSource->source);

        $rawSourceCreatedAt = $salesOrderSource->getAttribute('source_created_at');
        $this->assertEquals($sourceCreatedAt, \Carbon\Carbon::parse($rawSourceCreatedAt)->toDateTimeString());

        $this->assertEquals('12345', $salesOrderSource->source_order_id);
    }
}
