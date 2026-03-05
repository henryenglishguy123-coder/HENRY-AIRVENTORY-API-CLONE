<?php

namespace Tests\Unit\Services\Channels\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
use Mockery;
use Tests\TestCase;

class WooCommerceDataServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_ensure_product_relationships_loads_optimized_relations()
    {
        $service = new WooCommerceDataService(Mockery::mock(\App\Services\Currency\CurrencyConversionService::class));
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class);

        // Expect first load call (Template relations)
        $storeOverride->shouldReceive('load')->once()->with(Mockery::on(function ($relations) {
            return in_array('connectedStore', $relations) &&
                   in_array('template.product', $relations) &&
                   in_array('template.layers', $relations) &&
                   in_array('template.product.printingPrices', $relations) &&
                   ! in_array('variants.product.pricesWithMargin', $relations);
        }));

        // Expect second load call (Variant relations)
        $storeOverride->shouldReceive('load')->once()->with(Mockery::on(function ($relations) {
            return in_array('variants.product.pricesWithMargin', $relations) &&
                   in_array('variants.product.attributes.attribute', $relations);
        }));

        // Mock lazy eager loading check
        $template = Mockery::mock(VendorDesignTemplate::class);
        $storeOverride->shouldReceive('getAttribute')->with('template')->andReturn($template);
        // Assuming template->product is null or relationLoaded returns false to simplify test
        // Or we can mock it fully if needed. For now, let's just make it return null for product to skip the inner if.
        $template->shouldReceive('getAttribute')->with('product')->andReturn(null);

        $service->ensureProductRelationships($storeOverride);
        $this->assertTrue(true);
    }

    public function test_ensure_product_sku_generates_and_saves_sku_if_missing()
    {
        $this->markTestSkipped('ensureProductSku removed - dynamic generation now in prepareProductData');
    }

    public function test_ensure_product_sku_does_nothing_if_sku_exists()
    {
        $this->markTestSkipped('ensureProductSku removed - dynamic generation now in prepareProductData');
    }

    public function test_prepare_product_data_saves_sku_only_if_missing()
    {
        // Use partial mock to mock public helper calculatePrice
        $service = Mockery::mock(WooCommerceDataService::class, [
            Mockery::mock(\App\Services\Currency\CurrencyConversionService::class),
        ])->makePartial();

        $service->shouldReceive('calculatePrice')->andReturn(10.00);

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->sku = null; // SKU is missing
        $storeOverride->name = 'Test Product';
        $storeOverride->description = 'Test Description';
        $storeOverride->vendor_design_template_id = 100;
        $storeOverride->vendor_connected_store_id = 1;
        $storeOverride->id = 88;

        // Mock template relationship
        $template = Mockery::mock(VendorDesignTemplate::class);
        $catalogProduct = (object) ['sku' => 'BASE', 'weight' => 1.5];
        $template->shouldReceive('getAttribute')->with('product')->andReturn($catalogProduct);

        $storeOverride->shouldReceive('getAttribute')->with('template')->andReturn($template);
        $storeOverride->template = $template;

        // Mock variants (single variant to avoid prepareAttributes call)
        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([$variant]));

        // Mock images relationships to satisfy prepareImages (private method)
        $storeOverride->shouldReceive('getAttribute')->with('primaryImage')->andReturn(null);
        $storeOverride->shouldReceive('getAttribute')->with('syncImages')->andReturn(collect([]));

        // Expect save to be called once to persist the new SKU
        $storeOverride->shouldReceive('save')->once();

        $data = $service->prepareProductData($storeOverride);

        // Verify data structure
        $this->assertEquals('Test Product', $data['name']);

        // The SKU should be the generated one (Time + ID + UUID suffix)
        // We assert it contains the ID followed by a hyphen (separator for UUID)
        $this->assertStringContainsString((string) $storeOverride->id.'-', $data['sku']);
    }

    public function test_prepare_product_data_uses_existing_sku()
    {
        // Use partial mock to mock public helper calculatePrice
        $service = Mockery::mock(WooCommerceDataService::class, [
            Mockery::mock(\App\Services\Currency\CurrencyConversionService::class),
        ])->makePartial();

        $service->shouldReceive('calculatePrice')->andReturn(10.00);

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->sku = 'EXISTING-SKU'; // SKU Exists
        $storeOverride->name = 'Test Product';
        $storeOverride->description = 'Test Description';
        $storeOverride->vendor_design_template_id = 100;
        $storeOverride->vendor_connected_store_id = 1;
        $storeOverride->id = 88;

        // Mock template relationship
        $template = Mockery::mock(VendorDesignTemplate::class);
        $catalogProduct = (object) ['sku' => 'BASE', 'weight' => 1.5];
        $template->shouldReceive('getAttribute')->with('product')->andReturn($catalogProduct);

        $storeOverride->shouldReceive('getAttribute')->with('template')->andReturn($template);
        $storeOverride->template = $template;

        // Mock variants (single variant to avoid prepareAttributes call)
        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([$variant]));

        // Mock images relationships to satisfy prepareImages (private method)
        $storeOverride->shouldReceive('getAttribute')->with('primaryImage')->andReturn(null);
        $storeOverride->shouldReceive('getAttribute')->with('syncImages')->andReturn(collect([]));

        // Expect save NOT to be called because SKU exists
        $storeOverride->shouldReceive('save')->never();

        $data = $service->prepareProductData($storeOverride);

        // Verify SKU is preserved
        $this->assertEquals('EXISTING-SKU', $data['sku']);
    }

    public function test_prepare_variations_data_saves_sku_only_if_missing()
    {
        // Mock dependencies
        $service = Mockery::mock(WooCommerceDataService::class, [
            Mockery::mock(\App\Services\Currency\CurrencyConversionService::class),
        ])->makePartial();

        $service->shouldReceive('calculatePrice')->andReturn(20.00);

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->id = 55;
        $storeOverride->sku = 'PRODUCT-SKU-123'; // Product SKU exists

        // Mock template relationship for image logic
        $storeOverride->shouldReceive('getAttribute')->with('template')->andReturn(null);
        $storeOverride->template = null;
        $storeOverride->shouldReceive('getAttribute')->with('primaryImage')->andReturn(null);
        $storeOverride->shouldReceive('getAttribute')->with('syncImages')->andReturn(collect([]));

        // Mock Variant with missing SKU
        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->id = 777;
        $variant->sku = null; // Missing SKU
        $variant->external_variant_id = null;

        // Mock Product relationship
        $product = (object) ['id' => 99, 'attributes' => collect([]), 'weight' => 1.0];
        $variant->shouldReceive('getAttribute')->with('product')->andReturn($product);
        $variant->product = $product;

        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([$variant]));

        // Expect save to be called once to persist the new SKU
        $variant->shouldReceive('save')->once();

        $payload = $service->prepareVariationsData($storeOverride);
        $createData = $payload['create'][0];

        // Verify SKU generated and used
        $this->assertNotNull($createData['sku']);
        // Verify format: ProductSKU + VariantID
        // PRODUCT-SKU-123-777
        $this->assertEquals('PRODUCT-SKU-123-777', $createData['sku']);
    }

    public function test_prepare_variations_data_uses_existing_sku()
    {
        // Mock dependencies
        $service = Mockery::mock(WooCommerceDataService::class, [
            Mockery::mock(\App\Services\Currency\CurrencyConversionService::class),
        ])->makePartial();

        $service->shouldReceive('calculatePrice')->andReturn(20.00);

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class)->makePartial();
        $storeOverride->id = 55;
        // Mock template relationship for image logic
        $storeOverride->shouldReceive('getAttribute')->with('template')->andReturn(null);
        $storeOverride->template = null;
        $storeOverride->shouldReceive('getAttribute')->with('primaryImage')->andReturn(null);
        $storeOverride->shouldReceive('getAttribute')->with('syncImages')->andReturn(collect([]));

        // Mock Variant with EXISTING SKU
        $variant = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant->id = 777;
        $variant->sku = 'EXISTING-VARIANT-SKU'; // Exists
        $variant->external_variant_id = null;

        // Mock Product relationship
        $product = (object) ['id' => 99, 'attributes' => collect([]), 'weight' => 1.0];
        $variant->shouldReceive('getAttribute')->with('product')->andReturn($product);
        $variant->product = $product;

        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn(collect([$variant]));

        // Expect save NOT to be called
        $variant->shouldReceive('save')->never();

        $payload = $service->prepareVariationsData($storeOverride);
        $createData = $payload['create'][0];

        // Verify Existing SKU used
        $this->assertEquals('EXISTING-VARIANT-SKU', $createData['sku']);
    }

    public function test_get_variation_batches_splits_into_correct_chunks()
    {
        // Partial mock to bypass prepareVariationsData complexity
        $service = Mockery::mock(WooCommerceDataService::class, [
            Mockery::mock(\App\Services\Currency\CurrencyConversionService::class),
        ])->makePartial();

        // Generate 150 dummy variations (100 create, 50 update)
        $dummyCreate = array_fill(0, 100, ['dummy' => 'data']);
        $dummyUpdate = array_fill(0, 50, ['id' => 123]);

        $service->shouldReceive('prepareVariationsData')
            ->once()
            ->andReturn(['create' => $dummyCreate, 'update' => $dummyUpdate]);

        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class);

        // Test with batch size of 50
        $batches = iterator_to_array($service->getVariationBatches($storeOverride, 50));

        // Total items = 150. Batch size = 50. Expected batches = 3.
        $this->assertCount(3, $batches);

        // Batch 1: 50 creates
        $this->assertCount(50, $batches[0]['create']);
        $this->assertCount(0, $batches[0]['update']);

        // Batch 2: 50 creates
        $this->assertCount(50, $batches[1]['create']);
        $this->assertCount(0, $batches[1]['update']);

        // Batch 3: 50 updates (creates exhausted)
        $this->assertCount(0, $batches[2]['create']);
        $this->assertCount(50, $batches[2]['update']);
    }

    public function test_reconcile_variations_updates_local_id_and_returns_orphans()
    {
        $service = new WooCommerceDataService(Mockery::mock(\App\Services\Currency\CurrencyConversionService::class));
        $storeOverride = Mockery::mock(VendorDesignTemplateStore::class);

        // Mock Variants
        $variant1 = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant1->id = 1;
        $variant1->sku = 'SKU-1';
        $variant1->external_variant_id = null;

        $variant2 = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant2->id = 2;
        $variant2->sku = 'SKU-2';
        $variant2->external_variant_id = 999; // Wrong ID, should be updated

        $variant3 = Mockery::mock(VendorDesignTemplateStoreVariant::class)->makePartial();
        $variant3->id = 3;
        $variant3->sku = 'SKU-3';
        $variant3->external_variant_id = 500; // Correct ID

        $variants = collect([$variant1, $variant2, $variant3]);

        $storeOverride->shouldReceive('getAttribute')->with('variants')->andReturn($variants);

        // Mock WooCommerce Data
        $wooVariations = collect([
            [
                'id' => 101,
                'sku' => 'SKU-1', // Match by SKU
                'meta_data' => [],
            ],
            [
                'id' => 102,
                'sku' => 'SKU-UNKNOWN',
                'meta_data' => [
                    ['key' => '_vendor_variant_id', 'value' => 2], // Match by Meta
                ],
            ],
            [
                'id' => 500,
                'sku' => 'SKU-3', // Match existing
                'meta_data' => [],
            ],
            [
                'id' => 600,
                'sku' => 'SKU-OLD', // Orphan
                'meta_data' => [],
            ],
        ]);

        // Expect Saves
        $variant1->shouldReceive('save')->once(); // Should update to 101
        $variant2->shouldReceive('save')->once(); // Should update to 102
        $variant3->shouldReceive('save')->never(); // Should stay 500

        $orphans = $service->reconcileVariations($storeOverride, $wooVariations);

        // Verify IDs updated in memory objects
        $this->assertEquals(101, $variant1->external_variant_id);
        $this->assertEquals(102, $variant2->external_variant_id);

        // Verify orphans
        $this->assertCount(1, $orphans);
        $this->assertEquals(600, $orphans[0]);
    }
}
