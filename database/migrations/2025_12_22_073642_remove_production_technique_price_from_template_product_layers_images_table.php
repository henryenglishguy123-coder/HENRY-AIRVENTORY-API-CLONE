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
        if (Schema::hasTable('template_product_layers_images')) {
            Schema::table('template_product_layers_images', function (Blueprint $table) {
                if (Schema::hasColumn('template_product_layers_images', 'production_technique_price')) {
                    $table->dropColumn('production_technique_price');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('template_product_layers_images')) {
            Schema::table('template_product_layers_images', function (Blueprint $table) {
                if (! Schema::hasColumn('template_product_layers_images', 'production_technique_price')) {
                    $table->decimal('production_technique_price', 10, 2)->nullable();
                }
            });
        }
    }
};
