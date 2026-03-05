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
        if (Schema::hasTable('catalog_product_infos')) {
            Schema::table('catalog_product_infos', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_product_infos', 'store_id')) {
                    $table->dropColumn('store_id');
                }
                if (Schema::hasColumn('catalog_product_infos', 'stock_status')) {
                    $table->dropColumn('stock_status');
                }
                if (Schema::hasColumn('catalog_product_infos', 'meta_keywords')) {
                    $table->dropColumn('meta_keywords');
                }
                if (Schema::hasColumn('catalog_product_infos', 'index')) {
                    $table->dropColumn('index');
                }
                if (Schema::hasColumn('catalog_product_infos', 'search_keywords')) {
                    $table->dropColumn('search_keywords');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_infos', function (Blueprint $table) {
            $table->integer('store_id')->nullable()->after('catalog_product_id')->comment('Store ID');
            $table->string('stock_status')->nullable()->after('store_id')->comment('Stock status');
            $table->text('meta_keywords')->nullable()->after('stock_status')->comment('Meta keywords');
            $table->boolean('index')->default(true)->after('meta_keywords')->comment('Index');
            $table->boolean('is_featured')->default(false)->after('index')->comment('Is featured');
        });
    }
};
