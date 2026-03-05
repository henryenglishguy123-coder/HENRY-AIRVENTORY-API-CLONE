<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->string('platform', 50)
                ->comment('External platform e.g. shopify, woocommerce, manual');

            $table->string('source_order_id', 100)
                ->nullable()
                ->comment('External order ID');

            $table->string('source_order_number', 100)
                ->nullable()
                ->comment('External order number');

            $table->json('payload')
                ->nullable()
                ->comment('Raw external order payload');

            $table->timestamps();

            $table->unique(['cart_id', 'platform'], 'cart_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_sources');
    }
};
