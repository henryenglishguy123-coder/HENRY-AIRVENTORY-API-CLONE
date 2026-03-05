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
        Schema::create('factory_sales_routing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id');
            $table->unsignedMediumInteger('country_id');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->index(['country_id', 'priority']);
            $table->index('factory_id');
            $table->unique(['factory_id', 'country_id']);
            $table->foreign('factory_id')
                ->references('id')
                ->on('factory_users')
                ->onDelete('cascade');
            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_sales_routing');
    }
};
