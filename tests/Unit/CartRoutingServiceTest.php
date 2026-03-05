<?php

namespace Tests\Unit;

use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartError;
use App\Models\Customer\Cart\CartItem;
use App\Models\Factory\FactorySalesRouting;
use App\Services\Customer\Cart\CartPricingService;
use App\Services\Customer\Cart\CartRoutingService;
use App\Services\Customer\Cart\CartTotalsService;
use App\Services\Customer\Cart\InventoryService;
use App\Services\Tax\TaxResolverService;
use App\Support\Customers\CustomerMeta;
use Mockery;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class CartRoutingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_routing_no_factory_logs_error()
    {
        // Mock Dependencies
        $inventoryService = Mockery::mock(InventoryService::class);
        $pricingService = Mockery::mock(CartPricingService::class);
        $totalsService = Mockery::mock(CartTotalsService::class);
        $taxResolver = Mockery::mock(TaxResolverService::class);

        // Mock TaxResolver
        $taxResolver->shouldReceive('calculate')->andReturn([
            'tax' => null,
            'rate' => 0,
            'amount' => 0,
        ]);

        // Mock CartError
        $cartErrorMock = Mockery::mock('alias:'.CartError::class);
        $cartErrorMock->shouldReceive('where')->andReturnSelf(); // For delete()
        $cartErrorMock->shouldReceive('delete')->andReturn(true);
        $cartErrorMock->shouldReceive('exists')->andReturn(false); // Assume no other errors for now, or true to test hold
        // The service calls CartError::where()->exists() at the end.

        // Expect creation of error
        $cartErrorMock->shouldReceive('create')->withArgs(function ($args) {
            return $args['error_code'] === 'FACTORY_NOT_SELECTED';
        })->once();

        // Mock CustomerMeta
        $customerMetaMock = Mockery::mock('alias:'.CustomerMeta::class);
        $customerMetaMock->shouldReceive('get')->with(1, 'fulfillment_type', 'auto')->andReturn('manual');
        $customerMetaMock->shouldReceive('get')->with(1, 'allow_split_orders', false)->andReturn(false);

        // Mock Cart
        $cart = Mockery::mock(Cart::class)->makePartial();
        $cart->id = 1;
        $cart->vendor_id = 1;
        $cart->status = 'active';
        $cart->shouldReceive('save')->andReturn(true);
        $cart->shouldReceive('getAttribute')->with('address')->andReturn((object) ['country_id' => 1, 'state_id' => null, 'postal_code' => null]);

        // Mock Items
        $item = Mockery::mock(CartItem::class)->makePartial();
        $item->fulfillment_factory_id = null;
        $item->cart_id = 1;
        $item->line_total = 100; // Set a line total
        $item->tax_rate = 0;
        $item->tax_amount = 0;

        $cart->setRelation('items', collect([$item]));

        // Run Service
        $service = new CartRoutingService($inventoryService, $pricingService, $totalsService, $taxResolver);
        $service->processCartRouting($cart);
    }

    public function test_auto_routing_split_required_logs_error()
    {
        // Mock Dependencies
        $inventoryService = Mockery::mock(InventoryService::class);
        $pricingService = Mockery::mock(CartPricingService::class);
        $totalsService = Mockery::mock(CartTotalsService::class);
        $taxResolver = Mockery::mock(TaxResolverService::class);

        // Mock TaxResolver
        $taxResolver->shouldReceive('calculate')->andReturn([
            'tax' => null,
            'rate' => 0,
            'amount' => 0,
        ]);

        // Mock CartError
        $cartErrorMock = Mockery::mock('alias:'.CartError::class);
        $cartErrorMock->shouldReceive('where')->andReturnSelf();
        $cartErrorMock->shouldReceive('delete')->andReturn(true);
        $cartErrorMock->shouldReceive('exists')->andReturn(false);

        $cartErrorMock->shouldReceive('create')->withArgs(function ($args) {
            return $args['error_code'] === 'SPLIT_REQUIRED';
        })->once();

        // Mock CustomerMeta
        $customerMetaMock = Mockery::mock('alias:'.CustomerMeta::class);
        $customerMetaMock->shouldReceive('get')->with(1, 'fulfillment_type', 'auto')->andReturn('auto');
        $customerMetaMock->shouldReceive('get')->with(1, 'allow_split_orders', false)->andReturn(false);

        // Mock FactorySalesRouting
        $routingMock = Mockery::mock('alias:'.FactorySalesRouting::class);
        $routingMock->shouldReceive('query->where->orderBy->pluck->toArray')->andReturn([101, 102]);

        // Mock Cart
        $cart = Mockery::mock(Cart::class)->makePartial();
        $cart->id = 1;
        $cart->vendor_id = 1;
        $cart->status = 'active';
        $cart->shouldReceive('save')->andReturn(true);
        $cart->shouldReceive('getAttribute')->with('address')->andReturn((object) ['country_id' => 1, 'state_id' => null, 'postal_code' => null]);

        // Mock Items
        $item1 = Mockery::mock(CartItem::class)->makePartial();
        $item1->id = 1;
        $item1->qty = 1;
        $item1->cart_id = 1;
        $item1->fulfillment_factory_id = null;
        $item1->line_total = 50;
        $item1->tax_rate = 0;
        $item1->tax_amount = 0;
        $variant1 = Mockery::mock('App\Models\Catalog\Product\CatalogProduct'); // Mock Variant Model
        $item1->shouldReceive('getAttribute')->with('variant')->andReturn($variant1);

        $item2 = Mockery::mock(CartItem::class)->makePartial();
        $item2->id = 2;
        $item2->qty = 1;
        $item2->cart_id = 1;
        $item2->fulfillment_factory_id = null;
        $item2->line_total = 50;
        $item2->tax_rate = 0;
        $item2->tax_amount = 0;
        $variant2 = Mockery::mock('App\Models\Catalog\Product\CatalogProduct'); // Mock Variant Model
        $item2->shouldReceive('getAttribute')->with('variant')->andReturn($variant2);

        $cart->setRelation('items', collect([$item1, $item2]));

        // Inventory Mocking
        $inventoryService->shouldReceive('hasStockInFactory')->with($variant1, 101)->andReturn(true);
        $inventoryService->shouldReceive('hasStockInFactory')->with($variant2, 101)->andReturn(false);

        $inventoryService->shouldReceive('hasStockInFactory')->with($variant1, 102)->andReturn(false);
        $inventoryService->shouldReceive('hasStockInFactory')->with($variant2, 102)->andReturn(true);

        // Run Service
        $service = new CartRoutingService($inventoryService, $pricingService, $totalsService, $taxResolver);
        $service->processCartRouting($cart);
    }
}
