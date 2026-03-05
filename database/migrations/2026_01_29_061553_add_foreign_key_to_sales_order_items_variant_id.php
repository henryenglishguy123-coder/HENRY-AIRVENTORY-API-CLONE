<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_items')) {
            return;
        }

        /**
         * 1️⃣ Ensure column is nullable
         * (required so invalid rows don’t block FK creation)
         */
        if (Schema::hasColumn('sales_order_items', 'variant_id')) {
            try {
                Schema::table('sales_order_items', function (Blueprint $table) {
                    $table->unsignedBigInteger('variant_id')->nullable()->change();
                });
            } catch (\Exception $e) {
                // Ignore SQLite limitations or if change() fails
            }
        }

        /**
         * 2️⃣ Cleanup invalid references
         * (rows pointing to non-existing catalog_products)
         */
        if (Schema::hasColumn('sales_order_items', 'variant_id') && Schema::hasTable('catalog_products')) {
            DB::statement('
                UPDATE sales_order_items
                SET variant_id = NULL
                WHERE variant_id IS NOT NULL
                  AND variant_id NOT IN (
                      SELECT id FROM catalog_products
                  )
            ');
        }

        /**
         * 3️⃣ Add foreign key ONLY if not already exists
         */
        $fkExists = null;

        if (DB::getDriverName() !== 'sqlite') {
            $fkExists = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'sales_order_items'
                  AND COLUMN_NAME = 'variant_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
        }

        if (! $fkExists && Schema::hasColumn('sales_order_items', 'variant_id') && Schema::hasTable('catalog_products')) {
            try {
                Schema::table('sales_order_items', function (Blueprint $table) {
                    $table->foreign('variant_id')
                        ->references('id')
                        ->on('catalog_products')
                        ->nullOnDelete();
                });
            } catch (\Exception $e) {
                // Ignore if FK creation fails (e.g. SQLite constraints)
            }
        }
    }

    public function down(): void
    {
        /**
         * Drop FK if exists
         */
        $fk = null;
        if (DB::getDriverName() !== 'sqlite') {
            $fk = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'sales_order_items'
                  AND COLUMN_NAME = 'variant_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
        }

        if ($fk) {
            DB::statement(
                "ALTER TABLE sales_order_items DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}"
            );
        }

        /**
         * Optional: make column NOT NULL again
         * (only if business logic allows)
         */
        // Schema::table('sales_order_items', function (Blueprint $table) {
        //     $table->unsignedBigInteger('variant_id')->nullable(false)->change();
        // });
    }
};
