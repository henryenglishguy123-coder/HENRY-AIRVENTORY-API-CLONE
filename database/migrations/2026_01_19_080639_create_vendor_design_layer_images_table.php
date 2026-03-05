<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_design_layer_images', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('layer_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('vendor_id');

            $table->string('image');

            $table->timestamps();

            /* ======================
             | Indexes
             ====================== */
            $table->index(['template_id', 'layer_id']);
            $table->index(['product_id', 'variant_id']);
            $table->index('vendor_id');

            /* ======================
             | Foreign Keys (CASCADE)
             ====================== */
            $table->foreign('template_id')
                ->references('id')
                ->on('vendor_design_templates')
                ->cascadeOnDelete();

            $table->foreign('layer_id')
                ->references('id')
                ->on('catalog_design_template_layers')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('catalog_products')
                ->cascadeOnDelete();

            $table->foreign('variant_id')
                ->references('id')
                ->on('catalog_products')
                ->cascadeOnDelete();

            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_design_layer_images');
    }
};
