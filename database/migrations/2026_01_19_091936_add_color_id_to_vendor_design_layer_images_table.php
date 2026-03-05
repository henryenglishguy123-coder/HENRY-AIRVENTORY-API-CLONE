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
        Schema::table('vendor_design_layer_images', function (Blueprint $table) {
            $table->unsignedBigInteger('color_id')
                ->after('variant_id');
            $table->index('color_id');

            $table->foreign('color_id')
                ->references('option_id')
                ->on('catalog_attribute_options')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_design_layer_images', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropIndex(['color_id']);
            $table->dropColumn('color_id');
        });
    }
};
