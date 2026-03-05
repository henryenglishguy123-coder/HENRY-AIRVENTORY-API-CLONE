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
        // Drop only if table exists to avoid error
        Schema::dropIfExists('catalog_product_advance_prices');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('catalog_product_advance_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('catalog_product_id')
                ->constrained('catalog_products')
                ->cascadeOnDelete();

            // store_id probably relates to stores table, but leaving as integer since you used it
            $table->unsignedBigInteger('store_id')->index();

            $table->decimal('min_qty', 10, 2);
            $table->decimal('max_qty', 10, 2);
            $table->decimal('discount', 10, 2);

            $table->timestamps();
        });
    }
};
