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
        if (Schema::hasTable('catalog_products')) {
            Schema::table('catalog_products', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_products', 'factory_id')) {
                    $table->dropColumn('factory_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->integer('factory_id')->nullable();
        });
    }
};
