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
        if (Schema::hasTable('catalog_design_template_layers')) {
            Schema::table('catalog_design_template_layers', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_design_template_layers', 'catalog_template_design_id')) {
                    $table->renameColumn(
                        'catalog_template_design_id',
                        'catalog_design_template_id'
                    );
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('catalog_design_template_layers')) {
            Schema::table('catalog_design_template_layers', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_design_template_layers', 'catalog_design_template_id')) {
                    $table->renameColumn(
                        'catalog_design_template_id',
                        'catalog_template_design_id'
                    );
                }
            });
        }
    }
};
