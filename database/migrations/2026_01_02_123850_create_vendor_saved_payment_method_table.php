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
        Schema::create('vendor_saved_payment_method', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('vendor_id');
            $table->string('payment_method', 50);
            $table->string('saved_card_id');
            $table->string('card_type', 50)->nullable();
            $table->char('card_last_digit', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->cascadeOnDelete();
            $table->unique(['vendor_id', 'saved_card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_saved_payment_method');
    }
};
