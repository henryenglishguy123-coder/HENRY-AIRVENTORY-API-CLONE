<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // catalog_categories table updates
        Schema::table('catalog_categories', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_categories', 'tax_rule_id')) {
                $table->dropColumn('tax_rule_id');
            }
        });

        // catalog_category_metas table updates
        Schema::table('catalog_category_metas', function (Blueprint $table) {

            if (Schema::hasColumn('catalog_category_metas', 'created_at')) {
                $table->dropColumn('created_at');
            }

            if (Schema::hasColumn('catalog_category_metas', 'updated_at')) {
                $table->dropColumn('updated_at');
            }

            if (Schema::hasColumn('catalog_category_metas', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }

    public function down(): void
    {
        // catalog_categories table rollback
        Schema::table('catalog_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_categories', 'tax_rule_id')) {
                $table->unsignedBigInteger('tax_rule_id')->nullable();
            }
        });

        // catalog_category_metas table rollback
        Schema::table('catalog_category_metas', function (Blueprint $table) {

            if (! Schema::hasColumn('catalog_category_metas', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('catalog_category_metas', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }

            if (! Schema::hasColumn('catalog_category_metas', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable();
            }
        });
    }
};
