<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('template_product_layers_images')) {
            Schema::rename('template_product_layers_images', 'catalog_product_layer_images');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('catalog_product_layer_images')) {
            Schema::rename('catalog_product_layer_images', 'template_product_layers_images');
        }
    }
};
