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
        if (Schema::hasTable('catalog_product_design_template')) {
            Schema::rename('catalog_product_design_template', 'catalog_product_design_templates');
        }
        Schema::create('catalog_product_printing_price', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_product_id')
                ->constrained('catalog_products')
                ->onDelete('cascade');

            // 2. The Template Assignment ID
            $table->foreignId('catalog_product_design_template_id')
                ->constrained('catalog_product_design_templates')
                ->onDelete('cascade')
                ->name('fk_cppp_assignment');

            // 3. The Configuration IDs
            $table->foreignId('layer_id')
                ->constrained('catalog_design_template_layers')
                ->onDelete('cascade');

            $table->foreignId('factory_id')
                ->constrained('factory_users')
                ->onDelete('cascade');

            $table->foreignId('printing_technique_id')
                ->constrained('printing_techniques')
                ->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(
                [
                    'catalog_product_id',
                    'catalog_product_design_template_id',
                    'layer_id',
                    'factory_id',
                    'printing_technique_id',
                ],
                'unique_printing_price_combo'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_printing_price');
        Schema::rename('catalog_product_design_templates', 'catalog_product_design_template');
    }
};
