<?php

namespace Tests\Feature\Customer\Template;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Vendor;
use App\Models\Sales\Order\Item\SalesOrderItem;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TemplateDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalTables();
    }

    protected function createMinimalTables(): void
    {
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function ($table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->integer('account_status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_wallets')) {
            Schema::create('vendor_wallets', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 16, 4)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalog_design_template')) {
            Schema::create('catalog_design_template', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_design_templates')) {
            Schema::create('vendor_design_templates', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('catalog_design_template_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('sku')->nullable();
                $table->string('type')->default('simple');
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_design_layers')) {
            Schema::create('vendor_design_layers', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_design_template_id');
                $table->string('image_path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('printing_techniques')) {
            Schema::create('printing_techniques', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function ($table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('order_status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sales_order_items')) {
            Schema::create('sales_order_items', function ($table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('product_name')->nullable();
                $table->string('sku')->nullable();
                $table->integer('qty')->default(1);
                $table->timestamps();
            });
        }
    }

    protected function createCustomer(): Vendor
    {
        return Vendor::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'account_status' => 1,
        ]);
    }

    public function test_customer_can_delete_template_without_orders(): void
    {
        $customer = $this->createCustomer();
        $this->actingAs($customer, 'customer');

        $catalogTemplate = CatalogDesignTemplate::create([
            'name' => 'Base Template',
        ]);

        $template = VendorDesignTemplate::create([
            'vendor_id' => $customer->id,
            'catalog_design_template_id' => $catalogTemplate->id,
        ]);

        $response = $this->deleteJson("/api/v1/customers/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('vendor_design_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_customer_cannot_delete_template_with_orders(): void
    {
        $customer = $this->createCustomer();
        $this->actingAs($customer, 'customer');

        $catalogTemplate = CatalogDesignTemplate::create([
            'name' => 'Base Template',
        ]);

        $template = VendorDesignTemplate::create([
            'vendor_id' => $customer->id,
            'catalog_design_template_id' => $catalogTemplate->id,
        ]);

        $order = SalesOrder::create([
            'customer_id' => $customer->id,
            'order_status' => 'pending',
        ]);

        SalesOrderItem::create([
            'order_id' => $order->id,
            'template_id' => $template->id,
            'product_name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'qty' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/customers/templates/{$template->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'template_has_orders',
            ]);

        $this->assertDatabaseHas('vendor_design_templates', [
            'id' => $template->id,
        ]);
    }
}
