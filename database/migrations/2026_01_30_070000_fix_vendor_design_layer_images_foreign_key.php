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
        if (Schema::hasTable('vendor_design_layer_images')) {
            Schema::table('vendor_design_layer_images', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    // Check if the foreign key exists before dropping it
                    $fkExists = DB::selectOne(
                        "SELECT CONSTRAINT_NAME 
                         FROM information_schema.KEY_COLUMN_USAGE 
                         WHERE TABLE_NAME = 'vendor_design_layer_images' 
                         AND CONSTRAINT_NAME = 'vendor_design_layer_images_layer_id_foreign' 
                         AND TABLE_SCHEMA = DATABASE()"
                    );

                    if ($fkExists) {
                        $table->dropForeign(['layer_id']);
                    }
                }

                // Add the correct foreign key referencing catalog_design_template_layers
                if (Schema::hasTable('catalog_design_template_layers')) {
                    try {
                        $table->foreign('layer_id')
                            ->references('id')
                            ->on('catalog_design_template_layers')
                            ->cascadeOnDelete();
                    } catch (\Exception $e) {
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_design_layer_images', function (Blueprint $table) {
            // Drop the correct foreign key
            $table->dropForeign(['layer_id']);

            // Restore the incorrect foreign key referencing vendor_design_layers
            $table->foreign('layer_id')
                ->references('id')
                ->on('vendor_design_layers')
                ->cascadeOnDelete();
        });
    }
};
