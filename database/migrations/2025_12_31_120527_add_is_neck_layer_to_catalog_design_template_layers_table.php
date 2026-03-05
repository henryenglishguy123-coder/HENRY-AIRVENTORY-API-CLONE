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
            if (! Schema::hasColumn('catalog_design_template_layers', 'is_neck_layer')) {
                Schema::table('catalog_design_template_layers', function (Blueprint $table) {
                    $table
                        ->boolean('is_neck_layer')
                        ->default(false)
                        ->after('coordinates');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('catalog_design_template_layers')) {
            if (Schema::hasColumn('catalog_design_template_layers', 'is_neck_layer')) {
                Schema::table('catalog_design_template_layers', function (Blueprint $table) {
                    $table->dropColumn('is_neck_layer');
                });
            }
        }
    }
};
