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
        if (Schema::hasTable('catalog_design_template_layers')) {
            Schema::table('catalog_design_template_layers', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign('template_design_layers_template_design_id_foreign');
                }

                if (Schema::hasColumn('catalog_design_template_layers', 'template_design_id')) {
                    $table->renameColumn('template_design_id', 'catalog_template_design_id');
                }

                if (Schema::hasColumn('catalog_design_template_layers', 'background_image')) {
                    $table->renameColumn('background_image', 'image');
                }

                if (Schema::hasColumn('catalog_design_template_layers', 'coordinates')) {
                    $table->json('coordinates')->nullable()->change();
                }

                if (Schema::hasColumn('catalog_design_template_layers', 'catalog_template_design_id')) {
                    $table->foreign('catalog_template_design_id', 'layers_design_id_foreign')
                        ->references('id')->on('catalog_design_template')
                        ->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_design_template_layers', function (Blueprint $table) {
            $table->dropForeign('layers_design_id_foreign');
            $table->renameColumn('catalog_template_design_id', 'template_design_id');
            $table->renameColumn('image', 'background_image');
            $table->text('coordinates')->change();
            $table->foreign('template_design_id', 'template_design_layers_template_design_id_foreign')
                ->references('id')->on('catalog_design_template')
                ->onDelete('cascade');
        });
    }
};
