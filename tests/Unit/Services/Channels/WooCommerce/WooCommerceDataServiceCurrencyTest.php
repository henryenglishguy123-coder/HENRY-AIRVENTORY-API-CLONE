<?php

namespace Tests\Unit\Services\Channels\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use App\Services\Currency\CurrencyConversionService;
use Mockery;
use Tests\TestCase;

class WooCommerceDataServiceCurrencyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculate_price_converts_currency_when_store_currency_is_present()
    {
        // Arrange
        $currencyService = Mockery::mock(CurrencyConversionService::class);
        $service = new WooCommerceDataService($currencyService);

        $storeCurrency = 'EUR';
        $convertedPrice = 85.0;

        // Mock Store and Variant relationships
        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $connectedStore->currency = $storeCurrency;

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->connectedStore = $connectedStore;
        // Mock template relationships to avoid deep property access errors in calculatePrice
        // This is tricky because calculatePrice accesses deep relationships:
        // template->manufacturingFactory, product->pricesWithMargin, etc.
        // It's easier to use a simplified version or rely on the fact that calculatePrice
        // returns 0 or null if data is missing, BUT we want to test the CONVERSION at the end.

        // Let's mock calculatePrice's internal logic steps by setting up the mock objects carefully
        // Or better, let's look at calculatePrice again. It accesses $variant->product->pricesWithMargin.

        // To simplify, let's create a scenario where base calculation returns a known value (e.g. 0 + markup)
        // or just mock the relationships to return specific values.

        // However, since we are testing the service method which contains the logic, we have to provide input objects
        // that satisfy the method's requirements.

        // Let's assume the base price calculation part works (or returns 0) and focus on the markup/conversion.
        // If base is 0, and we add a fixed markup of 100, then price is 100.

        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->markup_type = 'fixed';
        $variant->markup = 100;
        // Mock product to avoid null check return
        $product = Mockery::mock('stdClass'); // Dummy product
        $product->pricesWithMargin = collect(); // Empty collection
        $product->printingPrices = collect(); // Fix: Add printingPrices
        $variant->product = $product;

        $template = Mockery::mock('stdClass');
        $template->manufacturingFactory = null;
        $template->layers = collect();
        $template->product = $product;

        $storeOverride->template = $template;

        // Expect conversion call
        $currencyService->shouldReceive('convert')
            ->once()
            ->with(100.0, $storeCurrency) // 0 base + 100 markup = 100
            ->andReturn($convertedPrice);

        // Act
        $result = $service->calculatePrice($variant, $storeOverride);

        // Assert
        $this->assertEquals($convertedPrice, $result);
    }

    public function test_calculate_price_skips_conversion_when_store_currency_is_missing()
    {
        // Arrange
        $currencyService = Mockery::mock(CurrencyConversionService::class);
        $service = new WooCommerceDataService($currencyService);

        // Mock Store without currency
        $connectedStore = Mockery::mock(VendorConnectedStore::class)->makePartial();
        $connectedStore->currency = null;

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->connectedStore = $connectedStore;

        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->markup_type = 'fixed';
        $variant->markup = 100;

        $product = Mockery::mock('stdClass');
        $product->pricesWithMargin = collect();
        $product->printingPrices = collect(); // Fix: Add printingPrices
        $variant->product = $product;

        $template = Mockery::mock('stdClass');
        $template->manufacturingFactory = null;
        $template->layers = collect();
        $template->product = $product;

        $storeOverride->template = $template;

        // Expect NO conversion call
        $currencyService->shouldReceive('convert')->never();

        // Act
        $result = $service->calculatePrice($variant, $storeOverride);

        // Assert
        $this->assertEquals(100.0, $result);
    }
}
