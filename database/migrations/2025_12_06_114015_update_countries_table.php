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
        if (Schema::hasTable('countries')) {
            Schema::table('countries', function (Blueprint $table) {
                if (Schema::hasColumn('countries', 'yunexpress_product_code')) {
                    $table->dropColumn('yunexpress_product_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
