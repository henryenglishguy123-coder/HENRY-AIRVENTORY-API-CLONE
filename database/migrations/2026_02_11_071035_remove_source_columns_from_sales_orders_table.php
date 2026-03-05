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
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn(['source', 'source_order_id', 'source_order_number']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('source')->default('web')->after('order_status');
                $table->string('source_order_id')->nullable()->after('source');
                $table->string('source_order_number')->nullable()->after('source_order_id');
            });
        }
    }
};
