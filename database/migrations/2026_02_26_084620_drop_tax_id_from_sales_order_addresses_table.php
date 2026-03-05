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
        Schema::table('sales_order_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_addresses', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_order_addresses', 'tax_id')) {
                if (Schema::hasColumn('sales_order_addresses', 'country')) {
                    $table->string('tax_id', 100)->nullable()->after('country');
                } else {
                    $table->string('tax_id', 100)->nullable();
                }
            }
        });
    }
};
