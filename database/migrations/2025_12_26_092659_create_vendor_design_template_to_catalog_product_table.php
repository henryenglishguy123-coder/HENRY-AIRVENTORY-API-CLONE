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
        Schema::create('vendor_design_template_to_catalog_product', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('vendor_design_template_id');
            $table->unsignedBigInteger('catalog_product_id');

            $table->timestamps();
            $table->foreign('vendor_id', 'fk_vdtcp_vendor')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');

            $table->foreign('vendor_design_template_id', 'fk_vdtcp_template')
                ->references('id')
                ->on('vendor_design_templates')
                ->onDelete('cascade');

            $table->foreign('catalog_product_id', 'fk_vdtcp_product')
                ->references('id')
                ->on('catalog_products')
                ->onDelete('cascade');
            $table->unique(
                ['vendor_id', 'vendor_design_template_id', 'catalog_product_id'],
                'uq_vdtcp_all'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_design_template_to_catalog_product');
    }
};
