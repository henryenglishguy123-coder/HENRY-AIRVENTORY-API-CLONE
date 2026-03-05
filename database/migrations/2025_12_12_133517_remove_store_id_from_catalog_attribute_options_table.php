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
        Schema::table('catalog_attribute_options', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_attribute_options', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_attribute_options', function (Blueprint $table) {
            Schema::table('catalog_attribute_options', function (Blueprint $table) {
                // Add it back in case of rollback
                $table->unsignedBigInteger('store_id')->nullable();
            });
        });
    }
};
