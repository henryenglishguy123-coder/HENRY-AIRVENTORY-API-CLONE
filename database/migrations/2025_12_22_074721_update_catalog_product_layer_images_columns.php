<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_product_layer_images')) {
            return;
        }
        DB::statement('
            DELETE FROM catalog_product_layer_images
            WHERE product_id NOT IN (SELECT id FROM catalog_products)
               OR layer_id NOT IN (SELECT id FROM catalog_design_template_layers)
               OR (option_id IS NOT NULL AND option_id NOT IN (SELECT id FROM catalog_attribute_options))
        ');
        try {
            Schema::table('catalog_product_layer_images', function (Blueprint $table) {
                $table->dropIndex(['layer_id']);
            });
        } catch (\Throwable $e) {
            // ignore if not exists
        }

        Schema::table('catalog_product_layer_images', function (Blueprint $table) {

            // Rename columns
            if (Schema::hasColumn('catalog_product_layer_images', 'product_id') &&
                ! Schema::hasColumn('catalog_product_layer_images', 'catalog_product_id')) {
                $table->renameColumn('product_id', 'catalog_product_id');
            }

            if (Schema::hasColumn('catalog_product_layer_images', 'layer_id') &&
                ! Schema::hasColumn('catalog_product_layer_images', 'catalog_design_template_layer_id')) {
                $table->renameColumn('layer_id', 'catalog_design_template_layer_id');
            }

            if (Schema::hasColumn('catalog_product_layer_images', 'option_id') &&
                ! Schema::hasColumn('catalog_product_layer_images', 'catalog_attribute_option_id')) {
                $table->renameColumn('option_id', 'catalog_attribute_option_id');
            }
        });

        // Add foreign keys
        Schema::table('catalog_product_layer_images', function (Blueprint $table) {

            if (Schema::hasColumn('catalog_product_layer_images', 'catalog_product_id')) {
                $table->foreign('catalog_product_id', 'fk_cpli_catalog_product_id')
                    ->references('id')
                    ->on('catalog_products')
                    ->onDelete('cascade');
            }

            if (Schema::hasColumn('catalog_product_layer_images', 'catalog_design_template_layer_id')) {
                $table->foreign('catalog_design_template_layer_id', 'fk_cpli_template_layer_id')
                    ->references('id')
                    ->on('catalog_design_template_layers')
                    ->onDelete('cascade');
            }

            if (Schema::hasColumn('catalog_product_layer_images', 'catalog_attribute_option_id')) {
                $table->foreign('catalog_attribute_option_id', 'fk_cpli_attribute_option_id')
                    ->references('option_id')
                    ->on('catalog_attribute_options')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalog_product_layer_images')) {
            return;
        }

        Schema::table('catalog_product_layer_images', function (Blueprint $table) {

            // Drop FKs
            $table->dropForeign(['catalog_product_id']);
            $table->dropForeign(['catalog_design_template_layer_id']);
            $table->dropForeign(['catalog_attribute_option_id']);

            // Rename columns back
            if (Schema::hasColumn('catalog_product_layer_images', 'catalog_product_id')) {
                $table->renameColumn('catalog_product_id', 'product_id');
            }

            if (Schema::hasColumn('catalog_product_layer_images', 'catalog_design_template_layer_id')) {
                $table->renameColumn('catalog_design_template_layer_id', 'layer_id');
            }

            if (Schema::hasColumn('catalog_product_layer_images', 'catalog_attribute_option_id')) {
                $table->renameColumn('catalog_attribute_option_id', 'option_id');
            }
        });
    }
};
