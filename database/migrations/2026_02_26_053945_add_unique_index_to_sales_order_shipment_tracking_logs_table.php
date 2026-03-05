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
        Schema::table('sales_order_shipment_tracking_logs', function (Blueprint $table) {
            $table->unique(['provider', 'provider_event_id'], 'shipment_tracking_provider_event_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_shipment_tracking_logs', function (Blueprint $table) {
            $table->dropUnique('shipment_tracking_provider_event_unique');
        });
    }
};
