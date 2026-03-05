<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_design_template_stores', 'is_link_only')) {
                $table->boolean('is_link_only')->default(false)->after('sync_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_design_template_stores', 'is_link_only')) {
                $table->dropColumn('is_link_only');
            }
        });
    }
};

