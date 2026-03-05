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
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_design_template_stores', 'primary_image_id')) {
                $table->dropForeign(['primary_image_id']);
                $table->dropColumn('primary_image_id');
            }
            if (Schema::hasColumn('vendor_design_template_stores', 'sync_image_id')) {
                $table->dropForeign(['sync_image_id']);
                $table->dropColumn('sync_image_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_image_id')->nullable()->after('description');
            $table->unsignedBigInteger('sync_image_id')->nullable()->after('primary_image_id');

            $table->foreign('primary_image_id')->references('id')->on('vendor_design_layer_images')->nullOnDelete();
            $table->foreign('sync_image_id')->references('id')->on('vendor_design_layer_images')->nullOnDelete();
        });
    }
};
