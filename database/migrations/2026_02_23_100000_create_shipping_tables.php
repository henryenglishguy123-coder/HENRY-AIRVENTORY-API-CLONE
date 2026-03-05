<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_partners')) {
            Schema::create('shipping_partners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('logo')->nullable();
                $table->string('code')->unique();
                $table->string('type')->default('both');
                $table->string('api_base_url')->nullable();
                $table->string('app_id')->nullable();
                $table->text('api_key')->nullable();
                $table->text('api_secret')->nullable();
                $table->text('webhook_secret')->nullable();
                $table->boolean('is_enabled')->default(true)->index();
                $table->string('last_sync_status')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sales_order_shipment_tracking_logs')) {
            Schema::create('sales_order_shipment_tracking_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id');
                $table->string('status', 64);
                $table->string('sub_status', 64)->nullable();
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->timestamp('checkpoint_time')->nullable();
                $table->json('raw_payload')->nullable();
                $table->string('provider', 64);
                $table->string('provider_event_id', 191)->nullable();
                $table->timestamps();

                $table->foreign('shipment_id')->references('id')->on('sales_order_shipments')->onDelete('cascade');
                $table->index(['shipment_id', 'checkpoint_time'], 'sos_track_ship_time_idx');
                $table->index(['provider', 'provider_event_id'], 'sos_track_provider_idx');
            });
        }

        if (! Schema::hasTable('sales_order_status_history')) {
            Schema::create('sales_order_status_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('from_status', 64)->nullable();
                $table->string('to_status', 64);
                $table->string('reason', 191)->nullable();
                $table->string('source', 32)->default('system');
                $table->unsignedBigInteger('shipping_partner_id')->nullable();
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('order_id')->references('id')->on('sales_orders')->onDelete('cascade');
                $table->foreign('shipping_partner_id')->references('id')->on('shipping_partners')->onDelete('set null');
                $table->foreign('shipment_id')->references('id')->on('sales_order_shipments')->onDelete('set null');
                $table->index(['order_id', 'created_at']);
                $table->index('shipping_partner_id');
                $table->index('shipment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('sales_order_status_history')) {
            Schema::dropIfExists('sales_order_status_history');
        }
        if (Schema::hasTable('sales_order_shipment_tracking_logs')) {
            Schema::dropIfExists('sales_order_shipment_tracking_logs');
        }
        if (Schema::hasTable('shipping_partners')) {
            Schema::dropIfExists('shipping_partners');
        }

        Schema::enableForeignKeyConstraints();
    }
};
