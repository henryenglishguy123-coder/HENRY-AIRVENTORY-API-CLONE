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
        if (Schema::hasTable('catalog_design_template')) {
            Schema::table('catalog_design_template', function (Blueprint $table) {
                if (! Schema::hasColumn('catalog_design_template', 'deleted_at')) {
                    $table->softDeletes()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_design_template', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
