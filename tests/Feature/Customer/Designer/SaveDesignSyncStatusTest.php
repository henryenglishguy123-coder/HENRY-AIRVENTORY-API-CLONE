<?php

namespace Tests\Feature\Customer\Designer;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignLayerImage;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateCatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;
use App\Services\Customer\Designer\StoreLayerImageAction;
use Illuminate\Foundation\Testing\RefreshDatabase; // Add this import
use Illuminate\Support\Facades\DB; // Add import
use Tests\TestCase; // Add import

class SaveDesignSyncStatusTest extends TestCase
{
    // use RefreshDatabase; // Removed to avoid transaction issues with manual DDL

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the action to return a dummy model
        $this->mock(StoreLayerImageAction::class, function ($mock) {
            $mock->shouldReceive('execute')->andReturn(new VendorDesignLayerImage);
        });

        // Manual Schema Creation for missing tables in SQLite
        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_layer_images')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_layer_images', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('template_id');
                $table->foreignId('layer_id');
                $table->foreignId('product_id');
                $table->foreignId('variant_id');
                $table->foreignId('color_id');
                $table->foreignId('vendor_id');
                $table->string('image')->nullable();
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendors')) {
            \Illuminate\Support\Facades\Schema::create('vendors', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('mobile')->nullable();
                $table->string('password')->nullable();
                $table->string('account_status')->nullable();
                $table->string('source')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_wallets')) {
            \Illuminate\Support\Facades\Schema::create('vendor_wallets', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->decimal('balance', 10, 2)->default(0);
                $table->string('currency')->default('USD');
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_product_parents')) {
            \Illuminate\Support\Facades\Schema::create('catalog_product_parents', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id');
                $table->foreignId('catalog_product_id');
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_product_attributes')) {
            \Illuminate\Support\Facades\Schema::create('catalog_product_attributes', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_product_id');
                $table->foreignId('attribute_id')->nullable(); // Assuming this points to attribute definition
                $table->string('attribute_value')->nullable(); // The option ID or value
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_products')) {
            \Illuminate\Support\Facades\Schema::create('catalog_products', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('slug')->nullable();
                $table->string('type')->default('simple');
                $table->string('sku')->nullable();
                $table->boolean('status')->default(1);
                $table->decimal('weight', 8, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_design_template')) {
            \Illuminate\Support\Facades\Schema::create('catalog_design_template', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('catalog_design_template_layers')) {
            \Illuminate\Support\Facades\Schema::create('catalog_design_template_layers', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_design_template_id');
                $table->string('layer_name')->nullable();
                $table->string('image')->nullable();
                $table->text('coordinates')->nullable();
                $table->boolean('is_neck_layer')->default(false);
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_templates')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_templates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->foreignId('catalog_design_template_id');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_template_to_catalog_product')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_template_to_catalog_product', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->foreignId('vendor_design_template_id');
                $table->foreignId('catalog_product_id');
                $table->foreignId('factory_id')->nullable();
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('factory_users')) {
            \Illuminate\Support\Facades\Schema::create('factory_users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->softDeletes(); // Add soft deletes
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_connected_stores')) {
            \Illuminate\Support\Facades\Schema::create('vendor_connected_stores', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->string('channel')->nullable();
                $table->string('store_identifier')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_template_stores')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_template_stores', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->foreignId('vendor_design_template_id');
                $table->foreignId('vendor_connected_store_id');
                $table->enum('sync_status', ['pending', 'syncing', 'synced', 'failed', 'disconnected'])->default('pending');
                $table->string('external_product_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('printing_techniques')) {
            \Illuminate\Support\Facades\Schema::create('printing_techniques', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_layers')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_layers', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_design_template_id');
                $table->foreignId('catalog_design_template_layer_id');
                $table->integer('technique_id')->nullable();
                $table->string('image_path')->nullable();
                $table->decimal('width', 10, 2)->default(0);
                $table->decimal('height', 10, 2)->default(0);
                $table->decimal('scale_x', 10, 2)->default(1);
                $table->decimal('scale_y', 10, 2)->default(1);
                $table->decimal('rotation_angle', 10, 2)->default(0);
                $table->decimal('position_top', 10, 2)->default(0);
                $table->decimal('position_left', 10, 2)->default(0);
                $table->text('canvas_json')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('vendor_design_template_catalog_products')) {
            \Illuminate\Support\Facades\Schema::create('vendor_design_template_catalog_products', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_design_template_id');
                $table->foreignId('vendor_id')->nullable();
                $table->foreignId('catalog_product_id')->nullable();
                $table->foreignId('factory_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_saving_synced_design_is_blocked()
    {
        // 1. Create Vendor (Customer)
        $vendor = Vendor::factory()->create();

        // 2. Create Catalog Product & Template
        $product = CatalogProduct::factory()->create();

        $catalogTemplate = CatalogDesignTemplate::create([
            'name' => 'Test Template',
            'status' => 'active',
        ]);

        $catalogLayer = CatalogDesignTemplateLayer::create([
            'catalog_design_template_id' => $catalogTemplate->id,
            'layer_name' => 'Front',
            'image' => 'test.png',
            'coordinates' => json_encode(['x' => 0, 'y' => 0]),
            'is_neck_layer' => false,
        ]);

        // 3. Create Vendor Template
        $vendorTemplate = VendorDesignTemplate::create([
            'vendor_id' => $vendor->id,
            'catalog_design_template_id' => $catalogTemplate->id,
        ]);

        // 4. Create Connected Store
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'status' => \App\Enums\Store\StoreConnectionStatus::CONNECTED,
        ]);

        // 5. Create Store Override (Synced)
        $override = VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_design_template_id' => $vendorTemplate->id,
            'vendor_connected_store_id' => $store->id,
            'sync_status' => 'synced', // Currently synced
            'external_product_id' => '123456',
        ]);

        // 6. Prepare Request Payload
        $payload = [
            'product_id' => $product->id,
            'template_id' => $catalogTemplate->id,
            'customer_template_id' => $vendorTemplate->id, // Update existing
            'layers' => [
                $catalogLayer->id => [
                    'technique_id' => 1,
                    'canvas' => [
                        'objects' => [
                            [
                                'type' => 'image',
                                'src' => 'http://example.com/image.png',
                                'width' => 100,
                                'height' => 100,
                            ],
                        ],
                    ],
                ],
            ],
            'images' => [
                $catalogLayer->id => [
                    1 => [
                        'image' => 'generated_image.png',
                    ],
                ],
            ],
        ];

        // Need Printing Technique
        DB::table('printing_techniques')->insertOrIgnore([
            'id' => 1,
            'name' => 'DTG',
            'slug' => 'dtg',
            'description' => 'Direct to Garment',
        ]);

        // 7. Act
        /** @var \Illuminate\Contracts\Auth\Authenticatable $vendor */
        $response = $this->actingAs($vendor, 'customer')
            ->postJson('/api/v1/catalog/designer/save', $payload);

        // 8. Assert - Should Fail
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['message' => 'This template is already synced with a store. Please duplicate it to edit.']);

        // Check if sync_status is UNCHANGED
        $this->assertDatabaseHas('vendor_design_template_stores', [
            'id' => $override->id,
            'sync_status' => 'synced',
        ]);
    }

    public function test_show_template_indicates_sync_status()
    {
        // 1. Create Vendor (Customer)
        $vendor = Vendor::factory()->create();

        // 2. Create Catalog Product & Template
        $product = CatalogProduct::factory()->create();

        $catalogTemplate = CatalogDesignTemplate::create([
            'name' => 'Test Template',
            'status' => 'active',
        ]);

        // 3. Create Vendor Template
        $vendorTemplate = VendorDesignTemplate::create([
            'vendor_id' => $vendor->id,
            'catalog_design_template_id' => $catalogTemplate->id,
        ]);

        // 4. Create Product association (needed for show)
        VendorDesignTemplateCatalogProduct::create([
            'vendor_design_template_id' => $vendorTemplate->id,
            'vendor_id' => $vendor->id,
            'catalog_product_id' => $product->id,
        ]);

        // 5. Create Connected Store
        $store = VendorConnectedStore::create([
            'vendor_id' => $vendor->id,
            'channel' => 'shopify',
            'store_identifier' => 'test-shop.myshopify.com',
            'status' => \App\Enums\Store\StoreConnectionStatus::CONNECTED,
        ]);

        // 6. Create Store Override (Synced)
        VendorDesignTemplateStore::create([
            'vendor_id' => $vendor->id,
            'vendor_design_template_id' => $vendorTemplate->id,
            'vendor_connected_store_id' => $store->id,
            'sync_status' => 'synced',
            'external_product_id' => '123456',
        ]);

        // Mock ProductDesignerController to avoid complex product queries
        $mockDesigner = \Mockery::mock(\App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerController::class);
        $mockDesigner->shouldReceive('index')->andReturn(response()->json([]));
        $this->app->instance(\App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerController::class, $mockDesigner);

        // 7. Act
        /** @var \Illuminate\Contracts\Auth\Authenticatable $vendor */
        $response = $this->actingAs($vendor, 'customer')
            ->getJson("/api/v1/customers/templates/{$vendorTemplate->id}");

        // 8. Assert
        $response->assertStatus(200);
        $response->assertJsonFragment(['is_synced' => true]);
        // Optionally check for message hint
    }
}
