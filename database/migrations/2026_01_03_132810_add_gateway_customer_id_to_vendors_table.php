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
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('gateway_customer_id')
                    ->nullable()
                    ->after('remember_token'); // adjust position if needed
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('gateway_customer_id');
            });
        }
    }
};
