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
                if (! Schema::hasColumn('catalog_products', 'weight')) {
                    $table->decimal('weight', 10, 2)->default(0.00)->after('type')->comment('Product weight');
                }
                if (Schema::hasColumn('catalog_products', 'hsn')) {
                    $table->dropColumn('hsn');
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
            $table->dropColumn('weight');
            $table->string('hsn')->nullable()->after('sku')->comment('Product HSN code');
        });
    }
};
