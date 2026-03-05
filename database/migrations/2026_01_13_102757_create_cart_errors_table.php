<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_errors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->string('key', 100)
                ->comment('Error key e.g. shipping_not_available, product_not_available');

            $table->text('description')
                ->comment('Readable error description');

            $table->timestamps();

            $table->unique(['cart_id', 'key'], 'cart_error_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_errors');
    }
};
