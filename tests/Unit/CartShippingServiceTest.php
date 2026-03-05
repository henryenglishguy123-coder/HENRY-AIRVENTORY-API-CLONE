<?php

namespace Tests\Unit;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartAddress;
use App\Models\Customer\Cart\CartItem;
use App\Services\Customer\Cart\CartShippingService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class CartShippingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * test_calculate_shipping_throws_exception_when_rate_missing
     */
    public function test_calculate_shipping_throws_exception_when_rate_missing()
    {
        // Mock Country Model
        // We use alias mock to intercept static calls
        $countryMock = Mockery::mock('alias:App\Models\Location\Country');
        $countryMock->shouldReceive('find')->with(1)->andReturn((object) ['iso2' => 'TC']);

        // Mock FactoryShippingRate Model
        $rateMock = Mockery::mock('alias:App\Models\Factory\FactoryShippingRate');
        $builderMock = Mockery::mock();

        // Chain: where('factory_id', ...)->where('country_code', ...)->where('min_qty', ...)->orderBy(...)->first()
        $rateMock->shouldReceive('where')->with('factory_id', 999)->andReturn($builderMock);
        $builderMock->shouldReceive('where')->with('country_code', 'TC')->andReturn($builderMock);
        $builderMock->shouldReceive('where')->with('min_qty', '<=', 10)->andReturn($builderMock);
        $builderMock->shouldReceive('orderBy')->with('min_qty', 'desc')->andReturn($builderMock);
        $builderMock->shouldReceive('orderBy')->with('price', 'asc')->andReturn($builderMock);
        $builderMock->shouldReceive('first')->andReturn(null); // Return null to trigger exception

        // Setup Cart Data (In Memory)
        $cart = new Cart;
        $cart->id = 1;

        $address = new CartAddress;
        $address->country_id = 1;
        $cart->setRelation('address', $address);

        $variant = new CatalogProduct;
        $variant->weight = 2.5;

        $item = new CartItem;
        $item->id = 101;
        $item->fulfillment_factory_id = 999;
        $item->qty = 10;
        $item->setRelation('variant', $variant);

        $cart->setRelation('items', new Collection([$item]));

        $service = new CartShippingService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No shipping rate found for factory ID 999 (Country: TC, Qty: 10). Please configure shipping rates.');

        $service->calculateShipping($cart);
    }

    /**
     * test_calculate_shipping_throws_exception_when_country_not_found
     */
    public function test_calculate_shipping_throws_exception_when_country_not_found()
    {
        // Mock Country Model to return null
        $countryMock = Mockery::mock('alias:App\Models\Location\Country');
        $countryMock->shouldReceive('find')->with(999)->andReturn(null);

        // Setup Cart Data
        $cart = new Cart;
        $cart->id = 123;

        $address = new CartAddress;
        $address->country_id = 999;
        $cart->setRelation('address', $address);

        // Items are needed to pass the initial empty check
        $item = new CartItem;
        $cart->setRelation('items', new Collection([$item]));

        $service = new CartShippingService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Country not found for ID 999 (Cart ID: 123). Cannot calculate shipping.');

        $service->calculateShipping($cart);
    }
}
