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
        Schema::create('sales_order_brandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained('sales_order_items')->cascadeOnDelete();
            $table->unsignedBigInteger('packaging_label_id')->nullable();
            $table->unsignedBigInteger('hang_tag_id')->nullable();

            // Applied versions (IDs from vendor_design_branding)
            $table->unsignedBigInteger('applied_packaging_label_id')->nullable();
            $table->unsignedBigInteger('applied_hang_tag_id')->nullable();

            // Prices
            $table->decimal('packaging_base_price', 15, 4)->default(0);
            $table->decimal('packaging_margin_price', 15, 4)->default(0);
            $table->decimal('packaging_total_price', 15, 4)->storedAs('packaging_base_price + packaging_margin_price');

            $table->decimal('hang_tag_base_price', 15, 4)->default(0);
            $table->decimal('hang_tag_margin_price', 15, 4)->default(0);
            $table->decimal('hang_tag_total_price', 15, 4)->storedAs('hang_tag_base_price + hang_tag_margin_price');

            $table->timestamps();

            $table->foreign('packaging_label_id')->references('id')->on('factory_packaging_labels')->nullOnDelete();
            $table->foreign('hang_tag_id')->references('id')->on('factory_hang_tags')->nullOnDelete();
            $table->foreign('applied_packaging_label_id')->references('id')->on('vendor_design_branding')->nullOnDelete();
            $table->foreign('applied_hang_tag_id')->references('id')->on('vendor_design_branding')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_brandings');
    }
};
