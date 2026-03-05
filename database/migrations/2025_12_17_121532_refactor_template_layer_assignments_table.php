<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename table
        if (
            Schema::hasTable('template_layer_assignments') &&
            ! Schema::hasTable('catalog_product_design_template')
        ) {
            Schema::rename(
                'template_layer_assignments',
                'catalog_product_design_template'
            );
        }

        if (! Schema::hasTable('catalog_product_design_template')) {
            return;
        }

        Schema::table('catalog_product_design_template', function (Blueprint $table) {

            // Drop factory_id FK + column
            if (Schema::hasColumn('catalog_product_design_template', 'factory_id')) {
                DB::statement(
                    'ALTER TABLE catalog_product_design_template 
                 DROP FOREIGN KEY template_layer_assignments_factory_id_foreign'
                );
                $table->dropColumn('factory_id');
            }

            // Drop layer_id FK + column
            if (Schema::hasColumn('catalog_product_design_template', 'layer_id')) {
                DB::statement(
                    'ALTER TABLE catalog_product_design_template 
                 DROP FOREIGN KEY template_layer_assignments_layer_id_foreign'
                );
                $table->dropColumn('layer_id');
            }

            // Rename product_id → catalog_product_id
            if (
                Schema::hasColumn('catalog_product_design_template', 'product_id') &&
                ! Schema::hasColumn('catalog_product_design_template', 'catalog_product_id')
            ) {
                DB::statement(
                    'ALTER TABLE catalog_product_design_template 
                 DROP FOREIGN KEY template_layer_assignments_product_id_foreign'
                );
                $table->renameColumn('product_id', 'catalog_product_id');
            }
        });
    }

    public function down(): void
    {
        // Optional: leave empty for safety
    }
};
