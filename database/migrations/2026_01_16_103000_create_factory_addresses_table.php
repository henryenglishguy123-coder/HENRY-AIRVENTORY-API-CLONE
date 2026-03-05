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
        Schema::create('factory_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id');
            $table->string('type', 25); // 'facility' or 'dist_center'
            $table->string('address');
            $table->string('country_id', 25);
            $table->string('state_id', 25);
            $table->string('city', 100);
            $table->string('postal_code', 10);
            $table->timestamps();

            $table->foreign('factory_id')->references('id')->on('factory_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_addresses');
    }
};
