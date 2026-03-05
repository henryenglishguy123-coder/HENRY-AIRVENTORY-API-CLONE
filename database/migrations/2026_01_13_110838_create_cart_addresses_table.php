<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();

            $table->string('city', 150);
            $table->string('state', 150)->nullable();
            $table->unsignedBigInteger('state_id')->nullable();

            $table->string('postal_code', 20);
            $table->string('country', 150)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_addresses');
    }
};
