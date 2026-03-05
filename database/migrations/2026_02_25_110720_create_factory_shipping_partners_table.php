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
        Schema::create('factory_shipping_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained('factory_users')->onDelete('cascade');
            $table->foreignId('shipping_partner_id')->constrained('shipping_partners')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['factory_id', 'shipping_partner_id'], 'factory_shipping_partner_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_shipping_partners');
    }
};
