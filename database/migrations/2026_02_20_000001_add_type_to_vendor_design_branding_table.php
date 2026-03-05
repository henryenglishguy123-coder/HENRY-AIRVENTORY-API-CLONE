<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_design_branding', function (Blueprint $table) {
            $table->string('type', 32)->default('branding')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_design_branding', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

