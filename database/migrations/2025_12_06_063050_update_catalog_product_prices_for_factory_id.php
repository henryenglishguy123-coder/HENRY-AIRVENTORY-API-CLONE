<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('catalog_product_prices')) {
            Schema::table('catalog_product_prices', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_product_prices', 'store_id')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['store_id']);
                    }
                    $table->dropColumn('store_id');
                }
                if (! Schema::hasColumn('catalog_product_prices', 'factory_id')) {
                    $table->unsignedBigInteger('factory_id')->nullable()->after('catalog_product_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable();
            $table->dropColumn('factory_id');
        });
    }
};
