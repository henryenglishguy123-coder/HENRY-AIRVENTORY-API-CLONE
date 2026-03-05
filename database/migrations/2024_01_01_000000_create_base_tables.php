<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. users (Admins)
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('username')->unique();
                $table->string('password');
                $table->string('mobile')->nullable();
                $table->string('user_type')->default('admin');
                $table->boolean('is_blocked')->default(0);
                $table->boolean('is_active')->default(1);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. vendors
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('mobile')->nullable();
                $table->string('google_id')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->string('source')->default('signup');
                $table->tinyInteger('account_status')->default(1);
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. sales_orders
        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('factory_id')->nullable();
                $table->unsignedBigInteger('cart_id')->nullable();
                $table->string('order_status')->default('pending');
                $table->string('payment_status')->default('pending');
                $table->decimal('grand_total', 15, 4)->default(0);
                $table->decimal('grand_total_inc_margin', 15, 4)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 4. sales_order_addresses (Legacy Version)
        if (! Schema::hasTable('sales_order_addresses')) {
            Schema::create('sales_order_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('address_type');
                $table->string('recipient_name')->nullable();
                $table->string('mobile_number')->nullable();
                $table->string('email_id')->nullable();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state_id')->nullable();
                $table->string('country_id')->nullable();
                $table->string('zip_code')->nullable();
                $table->timestamps();
            });
        }

        // 5. sales_order_items
        if (! Schema::hasTable('sales_order_items')) {
            Schema::create('sales_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('name')->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('price', 15, 4)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 6. sales_order_payments
        if (! Schema::hasTable('sales_order_payments')) {
            Schema::create('sales_order_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('payment_method')->nullable();
                $table->string('transaction_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 7. sales_order_errors
        if (! Schema::hasTable('sales_order_errors')) {
            Schema::create('sales_order_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('error_key')->nullable();
                $table->text('error_description')->nullable();
                $table->timestamps();
            });
        }

        // 8. vendor_wallets
        if (! Schema::hasTable('vendor_wallets')) {
            Schema::create('vendor_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('balance', 15, 4)->default(0);
                $table->timestamps();
            });
        }

        // 9. vendor_metas
        if (! Schema::hasTable('vendor_metas')) {
            Schema::create('vendor_metas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->string('key');
                $table->text('value')->nullable();
                $table->string('type')->default('string');
            });
        }

        // 10. currencies
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('currency');
                $table->string('code')->unique();
                $table->string('symbol');
                $table->string('localization_code')->nullable();
                $table->decimal('rate', 15, 8)->default(1);
                $table->boolean('is_allowed')->default(0);
                $table->boolean('is_default')->default(0);
                $table->timestamps();
            });
        }

        // 11. stores
        if (! Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('store_name');
                $table->string('icon')->nullable();
                $table->string('favicon')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 12. carts
        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        // 13. cart_items
        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cart_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('fulfillment_factory_id')->nullable();
                $table->string('sku')->nullable();
                $table->string('product_title')->nullable();
                $table->integer('qty')->default(1);
                $table->decimal('unit_price', 15, 4)->default(0);
                $table->decimal('line_total', 15, 4)->default(0);
                $table->decimal('tax_rate', 15, 4)->default(0);
                $table->decimal('tax_amount', 15, 4)->default(0);
                $table->timestamps();
            });
        }

        // 14. catalog_products
        if (! Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table) {
                $table->id();
                $table->string('type')->nullable();
                $table->string('slug')->unique();
                $table->string('sku')->unique()->nullable();
                $table->tinyInteger('status')->default(1);
                $table->decimal('weight', 10, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 16. catalog_product_parents
        if (! Schema::hasTable('catalog_product_parents')) {
            Schema::create('catalog_product_parents', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id');
                $table->unsignedBigInteger('catalog_product_id');
                $table->primary(['parent_id', 'catalog_product_id']);
            });
        }

        // 17. catalog_categories
        if (! Schema::hasTable('catalog_categories')) {
            Schema::create('catalog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 18. catalog_product_categories
        if (! Schema::hasTable('catalog_product_categories')) {
            Schema::create('catalog_product_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('catalog_product_id');
                $table->unsignedBigInteger('catalog_category_id');
                $table->primary(['catalog_product_id', 'catalog_category_id']);
            });
        }

        // 19. catalog_product_inventories
        if (! Schema::hasTable('catalog_product_inventories') && ! Schema::hasTable('catalog_product_inventory')) {
            Schema::create('catalog_product_inventories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('factory_id');
                $table->integer('quantity')->default(0);
                $table->tinyInteger('manage_inventory')->default(1);
                $table->tinyInteger('stock_status')->default(1);
                $table->timestamps();
            });
        }

        // 20. catalog_product_prices
        if (! Schema::hasTable('catalog_product_prices')) {
            Schema::create('catalog_product_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_product_id');
                $table->unsignedBigInteger('factory_id')->nullable();
                $table->decimal('sale_price', 15, 4)->nullable();
                $table->decimal('regular_price', 15, 4)->nullable();
                $table->timestamps();
            });
        }

        // 21. catalog_product_prices_with_margin
        if (! Schema::hasTable('catalog_product_prices_with_margin')) {
            Schema::create('catalog_product_prices_with_margin', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_product_id');
                $table->unsignedBigInteger('factory_id')->nullable();
                $table->decimal('sale_price', 15, 4)->nullable();
                $table->decimal('regular_price', 15, 4)->nullable();
                $table->timestamps();
            });
        }

        // 22. Seed default currency
        if (DB::table('currencies')->count() === 0) {
            DB::table('currencies')->insert([
                'currency' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'localization_code' => 'en_US',
                'rate' => 1.00000000,
                'is_allowed' => 1,
                'is_default' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_prices_with_margin');
        Schema::dropIfExists('catalog_product_prices');
        Schema::dropIfExists('catalog_product_inventories');
        Schema::dropIfExists('catalog_product_categories');
        Schema::dropIfExists('catalog_categories');
        Schema::dropIfExists('catalog_product_parents');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('vendor_metas');
        Schema::dropIfExists('vendor_wallets');
        Schema::dropIfExists('sales_order_errors');
        Schema::dropIfExists('sales_order_payments');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_order_addresses');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('vendor_users');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('users');
    }
};
