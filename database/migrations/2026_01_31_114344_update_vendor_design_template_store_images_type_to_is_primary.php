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
        // 1. Add new column
        Schema::table('vendor_design_template_store_images', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_design_template_store_images', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('image_path');
            }
        });

        // 2. Migrate data
        if (Schema::hasColumn('vendor_design_template_store_images', 'type')) {
            \Illuminate\Support\Facades\DB::table('vendor_design_template_store_images')
                ->where('type', 'primary')
                ->update(['is_primary' => true]);
        }

        // 3. Drop old column
        Schema::table('vendor_design_template_store_images', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_design_template_store_images', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add old column
        Schema::table('vendor_design_template_store_images', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_design_template_store_images', 'type')) {
                $table->enum('type', ['primary', 'sync'])->default('sync')->after('image_path');
            }
        });

        // 2. Migrate data
        if (Schema::hasColumn('vendor_design_template_store_images', 'is_primary')) {
            \Illuminate\Support\Facades\DB::table('vendor_design_template_store_images')
                ->where('is_primary', true)
                ->update(['type' => 'primary']);

            \Illuminate\Support\Facades\DB::table('vendor_design_template_store_images')
                ->where('is_primary', false)
                ->update(['type' => 'sync']);
        }

        // 3. Drop new column
        Schema::table('vendor_design_template_store_images', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_design_template_store_images', 'is_primary')) {
                $table->dropColumn('is_primary');
            }
        });
    }
};
