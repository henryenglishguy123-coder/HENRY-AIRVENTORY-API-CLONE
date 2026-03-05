<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_design_branding', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_design_branding', 'image_back')) {
                $table->dropColumn('image_back');
            }

            if (Schema::hasColumn('vendor_design_branding', 'width_back')) {
                $table->dropColumn('width_back');
            }

            if (Schema::hasColumn('vendor_design_branding', 'height_back')) {
                $table->dropColumn('height_back');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_design_branding', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_design_branding', 'image_back')) {
                $table->text('image_back')->nullable()->after('image');
            }

            if (! Schema::hasColumn('vendor_design_branding', 'width_back')) {
                $table->unsignedInteger('width_back')->nullable()->after('width');
            }

            if (! Schema::hasColumn('vendor_design_branding', 'height_back')) {
                $table->unsignedInteger('height_back')->nullable()->after('height');
            }
        });
    }
};

