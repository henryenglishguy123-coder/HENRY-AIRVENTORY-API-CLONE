<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Schema;

trait CreatesTestTables
{
    protected function createTestTables(): void
    {
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function ($table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('mobile')->nullable();
                $table->string('password')->nullable();
                $table->string('account_status')->default('active');
                $table->string('source')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('iso2');
                $table->string('currency')->nullable();
                $table->string('phone_code')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('states')) {
            Schema::create('states', function ($table) {
                $table->id();
                $table->foreignId('country_id');
                $table->string('name');
                $table->string('iso2');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_connected_stores')) {
            Schema::create('vendor_connected_stores', function ($table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->string('channel');
                $table->string('store_identifier');
                $table->string('link')->nullable();
                $table->string('token')->nullable();
                $table->string('currency')->nullable();
                $table->string('status')->default('active');
                $table->text('error_message')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('additional_data')->nullable();
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
                $table->decimal('weight', 8, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('catalog_product_parents')) {
            Schema::create('catalog_product_parents', function ($table) {
                $table->id();
                $table->foreignId('parent_id');
                $table->foreignId('catalog_product_id');
            });
        }

        if (! Schema::hasTable('catalog_design_template')) {
            Schema::create('catalog_design_template', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_design_templates')) {
            Schema::create('vendor_design_templates', function ($table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->foreignId('product_id')->nullable();
                $table->foreignId('catalog_design_template_id')->nullable();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_design_template_stores')) {
            Schema::create('vendor_design_template_stores', function ($table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->foreignId('vendor_connected_store_id');
                $table->foreignId('vendor_design_template_id');
                $table->string('external_product_id')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_design_template_store_variants')) {
            Schema::create('vendor_design_template_store_variants', function ($table) {
                $table->id();
                $table->foreignId('vendor_design_template_store_id');
                $table->foreignId('catalog_product_id');
                $table->string('sku')->nullable();
                $table->decimal('markup', 10, 2)->default(0);
                $table->string('markup_type')->default('fixed');
                $table->string('external_variant_id')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function ($table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function ($table) {
                $table->id();
                $table->foreignId('cart_id');
                $table->foreignId('product_id');
                $table->foreignId('variant_id')->nullable();
                $table->foreignId('template_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('product_title')->nullable();
                $table->integer('qty')->default(1);
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->decimal('line_total', 10, 2)->default(0);
                $table->foreignId('fulfillment_factory_id')->nullable();
                $table->foreignId('packaging_label_id')->nullable();
                $table->foreignId('hang_tag_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cart_addresses')) {
            Schema::create('cart_addresses', function ($table) {
                $table->id();
                $table->foreignId('cart_id');
                $table->string('type')->default('shipping');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->foreignId('state_id')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->nullable();
                $table->foreignId('country_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cart_sources')) {
            Schema::create('cart_sources', function ($table) {
                $table->id();
                $table->foreignId('cart_id');
                $table->string('platform', 50);
                $table->string('source')->nullable();
                $table->string('source_order_id', 100)->nullable();
                $table->string('source_order_number', 100)->nullable();
                $table->timestamp('source_created_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cart_errors')) {
            Schema::create('cart_errors', function ($table) {
                $table->id();
                $table->foreignId('cart_id');
                $table->string('sku')->nullable();
                $table->foreignId('factory_id')->nullable();
                $table->string('error_code');
                $table->string('error_message');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('factory_shipping_rates')) {
            Schema::create('factory_shipping_rates', function ($table) {
                $table->id();
                $table->foreignId('factory_id');
                $table->string('country_code');
                $table->decimal('rate', 10, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('cart_discounts')) {
            Schema::create('cart_discounts', function ($table) {
                $table->id();
                $table->foreignId('cart_id');
                $table->string('code')->nullable();
                $table->string('type')->default('percentage');
                $table->decimal('value', 15, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function ($table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->foreignId('customer_id');
                $table->foreignId('factory_id')->nullable();
                $table->foreignId('cart_id')->nullable();
                $table->string('order_status');
                $table->string('payment_status');
                $table->string('shipping_method')->nullable();
                $table->string('payment_method')->nullable();
                $table->text('tax_description')->nullable();

                // Base totals
                $table->decimal('base_subtotal_before_discount', 15, 4)->default(0);
                $table->decimal('base_subtotal', 15, 4)->default(0);
                $table->decimal('base_subtotal_tax', 15, 4)->default(0);
                $table->decimal('base_subtotal_inc_margin_before_discount', 15, 4)->default(0);
                $table->decimal('base_subtotal_inc_margin', 15, 4)->default(0);
                $table->decimal('base_subtotal_tax_inc_margin', 15, 4)->default(0);
                $table->decimal('base_total', 15, 4)->default(0);
                $table->decimal('base_total_inc_margin', 15, 4)->default(0);

                // Shipping
                $table->decimal('shipping_subtotal', 15, 4)->default(0);
                $table->decimal('shipping_subtotal_tax', 15, 4)->default(0);
                $table->decimal('shipping_total', 15, 4)->default(0);

                // Discounts
                $table->string('discount_description')->nullable();
                $table->decimal('base_discount', 15, 4)->default(0);
                $table->decimal('base_discount_inc_margin', 15, 4)->default(0);

                // Grand totals
                $table->decimal('grand_subtotal', 15, 4)->default(0);
                $table->decimal('grand_subtotal_tax', 15, 4)->default(0);
                $table->decimal('grand_subtotal_inc_margin', 15, 4)->default(0);
                $table->decimal('grand_subtotal_tax_inc_margin', 15, 4)->default(0);
                $table->decimal('grand_total', 15, 4)->default(0);
                $table->decimal('grand_total_inc_margin', 15, 4)->default(0);

                $table->string('remote_ip')->nullable();
                $table->timestamp('delivery_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('sales_order_sources')) {
            Schema::create('sales_order_sources', function ($table) {
                $table->id();
                $table->foreignId('order_id');
                $table->string('platform', 50);
                $table->string('source')->nullable();
                $table->string('source_order_id', 100)->nullable();
                $table->string('source_order_number', 100)->nullable();
                $table->timestamp('source_created_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('vendor_metas')) {
            Schema::create('vendor_metas', function ($table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('vendor_wallets')) {
            Schema::create('vendor_wallets', function ($table) {
                $table->id();
                $table->foreignId('vendor_id');
                $table->decimal('balance', 15, 4)->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('catalog_product_inventory')) {
            Schema::create('catalog_product_inventory', function ($table) {
                $table->id();
                $table->foreignId('product_id');
                $table->foreignId('factory_id')->nullable();
                $table->integer('quantity')->default(0);
                $table->integer('stock_status')->default(1);
                $table->boolean('manage_inventory')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('stores')) {
            Schema::create('stores', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('domain')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('catalog_product_prices')) {
            Schema::create('catalog_product_prices', function ($table) {
                $table->id();
                $table->foreignId('catalog_product_id');
                $table->foreignId('factory_id')->nullable();
                $table->decimal('regular_price', 10, 2)->nullable();
                $table->decimal('sale_price', 10, 2)->nullable();
                $table->decimal('specific_markup', 10, 2)->nullable();
            });
        }

        if (! Schema::hasTable('vendor_design_layers')) {
            Schema::create('vendor_design_layers', function ($table) {
                $table->id();
                $table->unsignedBigInteger('vendor_design_template_id');
                $table->unsignedBigInteger('catalog_design_template_layer_id');
                $table->unsignedBigInteger('technique_id');
                $table->enum('type', ['image'])->default('image');
                $table->string('image_path');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_sequences')) {
            Schema::create('order_sequences', function ($table) {
                $table->id();
                $table->string('prefix')->default('AIO');
                $table->unsignedBigInteger('current_value')->default(0);
                $table->string('last_order_number')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalog_product_attributes')) {
            Schema::create('catalog_product_attributes', function ($table) {
                $table->id();
                $table->foreignId('catalog_product_id');
                $table->foreignId('catalog_attribute_id');
                $table->string('attribute_value');
            });
        }

        if (! Schema::hasTable('sales_order_items')) {
            Schema::create('sales_order_items', function ($table) {
                $table->id();
                $table->foreignId('order_id');
                $table->foreignId('product_id');
                $table->foreignId('variant_id')->nullable();
                $table->foreignId('template_id')->nullable();

                // Product snapshot
                $table->string('product_name')->nullable();
                $table->string('catalog_name')->nullable();
                $table->string('sku')->nullable();

                // Weight
                $table->string('weight_unit')->nullable();
                $table->decimal('unit_weight', 10, 4)->default(0);

                // Pricing
                $table->decimal('factory_price', 10, 4)->default(0);
                $table->decimal('margin_price', 10, 4)->default(0);

                // Printing
                $table->json('printing_description')->nullable();
                $table->decimal('printing_cost', 10, 4)->default(0);

                // Unit pricing
                $table->decimal('row_price', 10, 4)->default(0);
                $table->decimal('row_price_inc_margin', 10, 4)->default(0);

                // Quantity & tax
                $table->integer('qty')->default(1);
                $table->decimal('tax_rate', 5, 2)->default(0);

                // Subtotals
                $table->decimal('subtotal', 15, 4)->default(0);
                $table->decimal('subtotal_tax', 15, 4)->default(0);
                $table->decimal('subtotal_inc_margin', 15, 4)->default(0);
                $table->decimal('subtotal_inc_margin_tax', 15, 4)->default(0);

                // Grand totals
                $table->decimal('grand_total', 15, 4)->default(0);
                $table->decimal('grand_total_inc_margin', 15, 4)->default(0);

                $table->timestamps();
            });
        }
        if (! Schema::hasTable('store_channels')) {
            Schema::create('store_channels', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('factory_packaging_labels')) {
            Schema::create('factory_packaging_labels', function ($table) {
                $table->id();
                $table->foreignId('factory_id');
                $table->string('name')->nullable();
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('factory_hang_tags')) {
            Schema::create('factory_hang_tags', function ($table) {
                $table->id();
                $table->foreignId('factory_id');
                $table->string('name')->nullable();
                $table->boolean('status')->default(1);
                $table->timestamps();
            });
        }
    }
}
