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
        if (! Schema::hasTable('sales_order_shipments')) {
            Schema::create('sales_order_shipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_order_id');
                $table->string('sales_shipment_number')->nullable();
                $table->string('tracking_name')->nullable();
                $table->string('tracking_number')->nullable();
                $table->text('tracking_url')->nullable();
                $table->string('label_type')->nullable();
                $table->text('label_url')->nullable();
                $table->string('waybill_number')->nullable();
                $table->text('bar_codes')->nullable();
                $table->decimal('total_quantity', 15, 2)->default(0);
                $table->decimal('total_weight', 15, 4)->default(0);
                $table->decimal('shipping_cost', 15, 4)->default(0);
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('sales_order_shipment_items')) {
            Schema::create('sales_order_shipment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_order_shipment_id');
                $table->unsignedBigInteger('sales_order_id');
                $table->unsignedBigInteger('sales_order_item_id');
                $table->string('sales_order_item_name')->nullable();
                $table->string('sales_order_item_sku')->nullable();
                $table->decimal('quantity', 15, 2);

                $table->foreign('sales_order_shipment_id', 'ssi_shipment_id_foreign')->references('id')->on('sales_order_shipments')->onDelete('cascade');
                $table->foreign('sales_order_id', 'ssi_order_id_foreign')->references('id')->on('sales_orders')->onDelete('cascade');
                $table->foreign('sales_order_item_id', 'ssi_item_id_foreign')->references('id')->on('sales_order_items')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('sales_order_shipment_addresses')) {
            Schema::create('sales_order_shipment_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_order_shipment_id');
                $table->string('address_type', 32); // shipping, billing
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->unsignedBigInteger('state_id')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->string('country')->nullable();
                $table->timestamps();

                $table->foreign('sales_order_shipment_id', 'ssa_shipment_id_foreign')->references('id')->on('sales_order_shipments')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_shipment_addresses');
        Schema::dropIfExists('sales_order_shipment_items');
        Schema::dropIfExists('sales_order_shipments');
    }
};
