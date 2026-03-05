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
        Schema::table('catalog_attribute_description', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_attribute_description', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_attribute_description', function (Blueprint $table) {
            // Recreate the column if rollback happens
            $table->unsignedBigInteger('store_id')->nullable();
        });
    }
};
