<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_design_branding', function (Blueprint $table) {
            $table->text('image_back')->nullable()->after('image');
            $table->unsignedInteger('width_back')->nullable()->after('width');
            $table->unsignedInteger('height_back')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_design_branding', function (Blueprint $table) {
            $table->dropColumn(['image_back', 'width_back', 'height_back']);
        });
    }
};
