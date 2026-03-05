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
            $table->unsignedBigInteger('hang_tag_id')->nullable()->after('is_link_only');
            $table->unsignedBigInteger('packaging_label_id')->nullable()->after('hang_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            $table->dropColumn(['hang_tag_id', 'packaging_label_id']);
        });
    }
};
