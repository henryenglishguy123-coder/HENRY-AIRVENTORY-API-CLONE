<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_user_cart_items') && ! Schema::hasTable('cart_items')) {
            Schema::rename('vendor_user_cart_items', 'cart_items');
        }

        if (DB::getDriverName() !== 'sqlite') {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'cart_items'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");

            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE cart_items DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
        }

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {

                if (Schema::hasColumn('cart_items', 'selected_attributes')) {
                    $table->dropColumn('selected_attributes');
                }

                if (
                    Schema::hasColumn('cart_items', 'catalog_id') &&
                    ! Schema::hasColumn('cart_items', 'template_id')
                ) {
                    $table->renameColumn('catalog_id', 'template_id');
                }
            });
        }

        if (Schema::hasTable('cart_items') && Schema::hasColumn('cart_items', 'quantity') && ! Schema::hasColumn('cart_items', 'qty')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->renameColumn('quantity', 'qty');
                // Ensure type/default if possible, but rename is safer for SQLite
                // $table->unsignedInteger('qty')->default(1)->change();
            });
        }

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {

                if (! Schema::hasColumn('cart_items', 'variant_id')) {
                    $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');
                }

                if (! Schema::hasColumn('cart_items', 'sku')) {
                    $table->string('sku', 100)->nullable()->after('variant_id');
                }

                if (! Schema::hasColumn('cart_items', 'product_title')) {
                    $table->string('product_title')->nullable()->after('sku');
                }

                if (! Schema::hasColumn('cart_items', 'unit_price')) {
                    $table->decimal('unit_price', 10, 4)->default(0)->after('product_title');
                }

                if (! Schema::hasColumn('cart_items', 'line_total')) {
                    $table->decimal('line_total', 10, 4)->nullable()->after('qty');
                }

                if (! Schema::hasColumn('cart_items', 'tax_rate')) {
                    $table->decimal('tax_rate', 10, 4)->nullable();
                }

                if (! Schema::hasColumn('cart_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 10, 4)->nullable();
                }

                if (! Schema::hasColumn('cart_items', 'created_at')) {
                    $table->timestamps();
                }

                // Unique Index
                $table->unique(['cart_id', 'product_id', 'variant_id'], 'cart_items_unique_item');

                // Foreign Keys
                if (Schema::hasTable('carts')) {
                    $table->foreign('cart_id', 'cart_items_cart_id_fk')
                        ->references('id')->on('carts')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('catalog_products')) {
                    $table->foreign('product_id', 'cart_items_product_id_fk')
                        ->references('id')->on('catalog_products')
                        ->onDelete('restrict');

                    $table->foreign('variant_id', 'cart_items_variant_id_fk')
                        ->references('id')->on('catalog_products')
                        ->onDelete('set null');
                }

                if (Schema::hasTable('vendor_design_templates')) {
                    $table->foreign('template_id', 'cart_items_template_id_fk')
                        ->references('id')->on('vendor_design_templates')
                        ->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_cart_id_fk');
            DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_product_id_fk');
            DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_variant_id_fk');
            DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_template_id_fk');
            DB::statement('DROP INDEX cart_items_unique_item ON cart_items');
        } else {
            Schema::table('cart_items', function (Blueprint $table) {
                // Drop index by name if possible, or just ignore as dropping columns might handle it
                // $table->dropUnique('cart_items_unique_item');
                // But schema builder might use array syntax
                try {
                    $table->dropUnique('cart_items_unique_item');
                } catch (\Exception $e) {
                    // ignore
                }
            });
        }

        Schema::table('cart_items', function (Blueprint $table) {

            if (Schema::hasColumn('cart_items', 'template_id')) {
                $table->renameColumn('template_id', 'catalog_id');
            }

            $table->dropColumn([
                'variant_id',
                'sku',
                'product_title',
                'unit_price',
                'line_total',
                'tax_rate',
                'tax_amount',
            ]);
        });

        if (Schema::hasColumn('cart_items', 'qty')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->renameColumn('qty', 'quantity');
            });
        }

        if (Schema::hasTable('cart_items')) {
            Schema::rename('cart_items', 'vendor_user_cart_items');
        }
    }
};
