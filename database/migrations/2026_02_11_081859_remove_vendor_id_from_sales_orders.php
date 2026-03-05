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
                if (Schema::hasColumn('sales_orders', 'vendor_id')) {
                    $table->dropColumn('vendor_id');
                }
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
                if (! Schema::hasColumn('sales_orders', 'vendor_id')) {
                    $table->foreignId('vendor_id')->nullable()->after('customer_id');
                }
            });
        }
    }
};
