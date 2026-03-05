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
        Schema::table('sales_order_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_order_shipments', 'status')) {
                $table->string('status', 64)->default('pending')->after('sales_order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_shipments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
