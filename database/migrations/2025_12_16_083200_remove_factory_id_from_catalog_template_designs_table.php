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
        if (Schema::hasTable('catalog_design_template')) {
            Schema::table('catalog_design_template', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_design_template', 'factory_id')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        try {
                            $table->dropForeign('template_designs_factory_id_foreign');
                        } catch (\Exception $e) {
                        }
                    }
                    $table->dropColumn('factory_id');
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
            $table->unsignedBigInteger('factory_id')->nullable();
        });
    }
};
