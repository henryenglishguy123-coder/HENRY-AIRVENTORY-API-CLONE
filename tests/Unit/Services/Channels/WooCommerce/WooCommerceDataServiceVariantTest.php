<?php

namespace Tests\Unit\Services\Channels\WooCommerce;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class WooCommerceDataServiceVariantTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_prepare_variations_data_handles_missing_product_relation()
    {
        $service = new WooCommerceDataService;

        // Mock Store Override
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->id = 1;
        $storeOverride->sku = 'PARENT-SKU';

        // Mock Variant 1: Valid
        $variant1 = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant1->id = 101;
        $variant1->external_variant_id = null;
        $variant1->sku = 'VAR-1';

        $product1 = Mockery::mock(CatalogProduct::class)->makePartial();
        $product1->weight = 0.5;
        $product1->attributes = new Collection; // Empty attributes for simplicity
        $product1->pricesWithMargin = new Collection;

        $variant1->shouldReceive('getAttribute')->with('product')->andReturn($product1);
        // $variant1->setRelation('product', $product1); // Removed

        // Mock Variant 2: Missing Product (Broken Relation)
        $variant2 = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant2->id = 102;
        $variant2->external_variant_id = null;
        $variant2->sku = 'VAR-2';

        $variant2->shouldReceive('getAttribute')->with('product')->andReturn(null);
        // $variant2->setRelation('product', null); // Removed

        // Set variants on store
        $storeOverride->variants = new Collection([$variant1, $variant2]);

        // Expect no exception
        $result = $service->prepareVariationsData($storeOverride);

        $this->assertCount(2, $result['create']);

        // Check Variant 1
        $this->assertEquals('VAR-1', $result['create'][0]['sku']);

        // Check Variant 2
        $this->assertEquals('VAR-2', $result['create'][1]['sku']);
        $this->assertEquals('', $result['create'][1]['regular_price']); // Expect empty string if price null
    }

    public function test_prepare_variations_data_generates_sku_if_missing()
    {
        $service = new WooCommerceDataService;
        $service = Mockery::mock(WooCommerceDataService::class)->makePartial(); // To mock internal methods if needed, but we test real logic

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->sku = 'PARENT';
        $storeOverride->vendor_design_template_id = 99;
        $storeOverride->vendor_connected_store_id = 10;

        // Mock template relationship to avoid DB query
        $storeOverride->shouldReceive('getAttribute')->with('template')->andReturn(null);

        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->sku = null;
        $variant->id = 555;
        $variant->external_variant_id = null;

        // Mock Product with attributes for SKU generation
        $product = Mockery::mock(CatalogProduct::class)->makePartial();

        // Mock Attributes
        $attrOption = (object) ['key' => 'Blue'];
        $attrAttribute = (object) ['description' => (object) ['name' => 'Color'], 'attribute_code' => 'color'];
        $prodAttr = (object) ['option' => $attrOption, 'attribute' => $attrAttribute];
        $product->attributes = new Collection([$prodAttr]);
        $product->pricesWithMargin = new Collection;

        $variant->shouldReceive('getAttribute')->with('product')->andReturn($product);

        $storeOverride->variants = new Collection([$variant]);

        $result = $service->prepareVariationsData($storeOverride);

        $this->assertNotNull($result['create'][0]['sku']);
        $this->assertStringContainsString('PARENT-BLUE', $result['create'][0]['sku']);
    }
}
