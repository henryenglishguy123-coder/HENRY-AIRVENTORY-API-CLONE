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
        Schema::create('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_design_template_store_id');

            $table->foreign(
                'vendor_design_template_store_id',
                'vdt_store_variants_store_fk'
            )
                ->references('id')
                ->on('vendor_design_template_stores')
                ->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('external_variant_id')->nullable()->comment('Store specific variant ID');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_design_template_store_variants');
    }
};
