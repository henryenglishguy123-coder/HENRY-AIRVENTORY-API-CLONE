<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductInfo;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductParent;
use App\Models\Catalog\Product\CatalogProductPrice;
use App\Models\Factory\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalTables();
    }

    protected function createMinimalTables(): void
    {
        if (! Schema::hasTable('factory_users')) {
            Schema::create('factory_users', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('phone_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('password')->nullable();
                $table->string('source')->nullable();
                $table->integer('account_status')->default(1);
                $table->integer('email_verified')->default(1);
                $table->integer('account_verified')->default(1);
                $table->string('remember_token')->nullable();
                $table->integer('catalog_status')->default(1);
                $table->string('stripe_account_id')->nullable();
                $table->string('email_verification_code')->nullable();
                $table->timestamp('email_verification_code_expires_at')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('username')->unique();
                $table->string('password')->nullable();
                $table->string('mobile')->nullable();
                $table->string('user_type')->default('admin');
                $table->boolean('is_blocked')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('catalog_product_infos')) {
            Schema::create('catalog_product_infos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_product_id');
                $table->string('name')->nullable();
                $table->text('short_description')->nullable();
                $table->text('description')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('catalog_product_files')) {
            Schema::create('catalog_product_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_product_id');
                $table->string('image')->nullable();
                $table->string('type')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('catalog_product_attributes')) {
            Schema::create('catalog_product_attributes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_product_id');
                $table->unsignedBigInteger('catalog_attribute_id')->nullable();
                $table->string('attribute_value')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('sku')->nullable();
                $table->string('type')->default('simple');
                $table->boolean('status')->default(1);
                $table->decimal('weight', 8, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('catalog_product_parents')) {
            Schema::create('catalog_product_parents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id');
                $table->foreignId('catalog_product_id');
            });
        }
        if (! Schema::hasTable('catalog_product_inventory')) {
            Schema::create('catalog_product_inventory', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id');
                $table->foreignId('factory_id')->nullable();
                $table->integer('quantity')->default(0);
                $table->integer('stock_status')->default(1);
                $table->boolean('manage_inventory')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('catalog_product_prices')) {
            Schema::create('catalog_product_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_product_id');
                $table->foreignId('factory_id')->nullable();
                $table->decimal('regular_price', 10, 2)->nullable();
                $table->decimal('sale_price', 10, 2)->nullable();
                $table->decimal('specific_markup', 10, 2)->nullable();
            });
        }
    }

    protected function createVerifiedFactory(array $attributes = []): Factory
    {
        $factory = Factory::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'factory_'.uniqid().'@factory.com',
            'phone_number' => '+1234567890',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'account_status' => 1,
        ], $attributes));

        return $factory;
    }

    protected function seedProductWithRelations(Factory $factory): array
    {
        $parent = CatalogProduct::create([
            'type' => 'configurable',
            'slug' => 'parent-product',
            'sku' => 'PARENT-SKU',
            'status' => 1,
            'weight' => 0,
        ]);
        CatalogProductInventory::create([
            'product_id' => $parent->id,
            'manage_inventory' => 1,
            'quantity' => 100,
            'stock_status' => 1,
        ]);
        $variant = CatalogProduct::create([
            'type' => 'simple',
            'slug' => 'variant-product',
            'sku' => 'SKU-001',
            'status' => 1,
            'weight' => 0,
        ]);
        CatalogProductParent::create([
            'catalog_product_id' => $variant->id,
            'parent_id' => $parent->id,
        ]);
        CatalogProductInfo::create([
            'catalog_product_id' => $variant->id,
            'name' => 'Variant 001',
        ]);
        CatalogProductPrice::create([
            'catalog_product_id' => $variant->id,
            'regular_price' => 100.0,
            'sale_price' => null,
        ]);
        CatalogProductPrice::create([
            'catalog_product_id' => $variant->id,
            'factory_id' => $factory->id,
            'regular_price' => 150.0,
            'sale_price' => 130.0,
        ]);
        CatalogProductInventory::create([
            'product_id' => $variant->id,
            'factory_id' => $factory->id,
            'manage_inventory' => 1,
            'quantity' => 5,
            'stock_status' => 0,
        ]);

        return [$parent, $variant];
    }

    public function test_index_requires_factory_context_for_admin(): void
    {
        $admin = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'username' => 'testadmin',
            'password' => Hash::make('password123'),
            'mobile' => '1234567890',
            'user_type' => 'admin',
            'is_blocked' => 0,
            'is_active' => 1,
        ]);
        $token = auth('admin_api')->login($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/factories/inventory');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Factory context required',
            ]);
    }

    public function test_index_returns_items_for_factory_user(): void
    {
        $factory = $this->createVerifiedFactory();
        [$parent, $variant] = $this->seedProductWithRelations($factory);
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/factories/inventory');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Factory products retrieved successfully.',
            ]);
        $this->assertNotEmpty($response->json('data'));
        $first = $response->json('data')[0];
        $this->assertEquals($variant->id, $first['id']);
        $this->assertEquals('SKU-001', $first['sku']);
    }

    public function test_update_skips_stock_status_when_null(): void
    {
        $factory = $this->createVerifiedFactory();
        [$parent, $variant] = $this->seedProductWithRelations($factory);
        $token = auth('factory')->login($factory);

        $invBefore = CatalogProductInventory::where('product_id', $variant->id)
            ->where('factory_id', $factory->id)
            ->first();
        $this->assertEquals(0, (int) $invBefore->stock_status);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/inventory/update', [
            'items' => [
                [
                    'id' => $variant->id,
                    'stock_status' => null,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Factory products updated successfully.',
            ]);

        $invAfter = CatalogProductInventory::where('product_id', $variant->id)
            ->where('factory_id', $factory->id)
            ->first();
        $this->assertEquals(0, (int) $invAfter->stock_status);
    }

    public function test_update_does_not_clear_prices_when_omitted(): void
    {
        $factory = $this->createVerifiedFactory();
        [$parent, $variant] = $this->seedProductWithRelations($factory);
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/inventory/update', [
            'items' => [
                [
                    'id' => $variant->id,
                    'quantity' => 10,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Factory products updated successfully.',
            ]);

        $price = CatalogProductPrice::where('catalog_product_id', $variant->id)
            ->where('factory_id', $factory->id)
            ->first();
        $this->assertEquals(150.0, (float) $price->regular_price);
        $this->assertEquals(130.0, (float) $price->sale_price);
    }

    public function test_export_and_import_csv_round_trip_preserves_prices_when_blank(): void
    {
        $factory = $this->createVerifiedFactory();
        [$parent, $variant] = $this->seedProductWithRelations($factory);
        $token = auth('factory')->login($factory);

        $header = 'variant_id,sku,name,parent_name,quantity,stock_status,regular_price,sale_price';
        $factoryInv = CatalogProductInventory::where('product_id', $variant->id)
            ->where('factory_id', $factory->id)
            ->first();
        $data = [
            $variant->id,
            'SKU-001',
            'Variant 001',
            '',
            (string) ($factoryInv->quantity ?? ''),
            (string) ($factoryInv->stock_status ?? ''),
            '',
            '',
        ];
        $newLine = implode(',', array_map(function ($v) {
            return is_numeric($v) ? $v : '"'.str_replace('"', '""', $v).'"';
        }, $data));
        $modifiedCsv = $header."\n".$newLine."\n";

        $tmp = tmpfile();
        fwrite($tmp, $modifiedCsv);
        $meta = stream_get_meta_data($tmp);
        $path = $meta['uri'];

        $file = new UploadedFile(
            $path,
            'factory_products.csv',
            'text/csv',
            null,
            true
        );

        $import = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/v1/factories/inventory/import', [
            'file' => $file,
        ]);
        $import->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Factory products imported successfully.',
            ]);

        $price = CatalogProductPrice::where('catalog_product_id', $variant->id)
            ->where('factory_id', $factory->id)
            ->first();
        $this->assertEquals(150.0, (float) $price->regular_price);
        $this->assertEquals(130.0, (float) $price->sale_price);
    }
}
