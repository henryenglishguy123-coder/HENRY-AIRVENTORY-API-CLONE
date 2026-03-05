<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalog_product_design_template')) {
            Schema::table('catalog_product_design_template', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_product_design_template', 'catalog_design_template_id')) {
                    $table->foreign('catalog_design_template_id', 'cpdt_catalog_design_template_id_fk')
                        ->references('id')->on('catalog_design_template')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('catalog_product_design_template')) {
            Schema::table('catalog_product_design_template', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign('cpdt_catalog_design_template_id_fk');
                }
            });
        }
    }
};
