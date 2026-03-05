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
        Schema::table('catalog_product_inventory', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_product_inventory', 'min_quantity')) {
                $table->dropColumn('min_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_inventory', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_product_inventory', 'min_quantity')) {
                $table->integer('min_quantity')->default(0)->after('quantity');
            }
        });
    }
};
