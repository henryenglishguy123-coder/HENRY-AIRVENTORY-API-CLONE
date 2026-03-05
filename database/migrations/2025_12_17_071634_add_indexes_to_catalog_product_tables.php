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
        // catalog_product_infos (used for title sorting)
        if (Schema::hasTable('catalog_product_infos')) {
            Schema::table('catalog_product_infos', function (Blueprint $table) {
                $table->index(
                    ['catalog_product_id', 'name'],
                    'idx_product_infos_product_name'
                );
            });
        }

        // catalog_product_files (used for image eager loading)
        if (Schema::hasTable('catalog_product_files')) {
            Schema::table('catalog_product_files', function (Blueprint $table) {
                $table->index(
                    ['catalog_product_id', 'type', 'order'],
                    'idx_product_files_product_type_order'
                );
            });
        }

        // catalog_product_parents (used in joins)
        if (Schema::hasTable('catalog_product_parents')) {
            Schema::table('catalog_product_parents', function (Blueprint $table) {
                $table->index(
                    'parent_id',
                    'idx_product_parents_parent_id'
                );

                $table->index(
                    'catalog_product_id',
                    'idx_product_parents_catalog_product_id'
                );
            });
        }

        // catalog_product_prices
        if (Schema::hasTable('catalog_product_prices')) {
            Schema::table('catalog_product_prices', function (Blueprint $table) {
                $table->index(
                    'catalog_product_id',
                    'idx_product_prices_product_id'
                );

                // Optional but great for heavy price reads
                $table->index(
                    ['catalog_product_id', 'sale_price', 'regular_price'],
                    'idx_product_prices_product_sale_regular'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_infos', function (Blueprint $table) {
            $table->dropIndex('idx_product_infos_product_name');
        });

        Schema::table('catalog_product_files', function (Blueprint $table) {
            $table->dropIndex('idx_product_files_product_type_order');
        });

        Schema::table('catalog_product_parents', function (Blueprint $table) {
            $table->dropIndex('idx_product_parents_parent_id');
            $table->dropIndex('idx_product_parents_catalog_product_id');
        });

        Schema::table('catalog_product_prices', function (Blueprint $table) {
            $table->dropIndex('idx_product_prices_product_id');
            $table->dropIndex('idx_product_prices_product_sale_regular');
        });
    }
};
