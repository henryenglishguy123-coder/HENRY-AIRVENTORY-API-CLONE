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
            if (! Schema::hasColumn('sales_order_shipments', 'external_shipment_id')) {
                $table->string('external_shipment_id')->nullable()->after('waybill_number');
            }
            if (! Schema::hasColumn('sales_order_shipments', 'label_id')) {
                $table->string('label_id')->nullable()->after('external_shipment_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_shipments', 'label_id')) {
                $table->dropColumn('label_id');
            }
            if (Schema::hasColumn('sales_order_shipments', 'external_shipment_id')) {
                $table->dropColumn('external_shipment_id');
            }
        });
    }
};
