<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_item_id');
            $table->string('option_code', 50);
            $table->string('option_value', 100);
            $table->timestamps();

            $table->foreign('cart_item_id')
                ->references('id')
                ->on('cart_items')
                ->onDelete('cascade');

            $table->index('cart_item_id');
        });

        Schema::create('cart_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->enum('source', ['coupon', 'role', 'bulk', 'campaign']);
            $table->string('code', 50)->nullable();
            $table->decimal('amount', 10, 4);
            $table->timestamps();

            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->onDelete('cascade');

            $table->index('cart_id');
        });

        Schema::create('cart_shipping_selected', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_id')->primary();
            $table->string('carrier', 100);
            $table->string('method', 100);
            $table->decimal('price', 10, 4);
            $table->decimal('tax', 10, 4)->default(0);
            $table->timestamps();

            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->onDelete('cascade');
        });

        Schema::create('cart_totals', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_id')->primary();
            $table->decimal('subtotal', 10, 4);
            $table->decimal('tax_total', 10, 4);
            $table->decimal('discount_total', 10, 4);
            $table->decimal('shipping_total', 10, 4);
            $table->decimal('grand_total', 10, 4);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_totals');
        Schema::dropIfExists('cart_shipping_selected');
        Schema::dropIfExists('cart_discounts');
        Schema::dropIfExists('cart_item_options');
    }
};
