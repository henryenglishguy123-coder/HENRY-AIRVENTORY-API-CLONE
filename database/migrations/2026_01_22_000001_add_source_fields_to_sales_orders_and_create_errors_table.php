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
                if (! Schema::hasColumn('sales_orders', 'source')) {
                    $table->string('source')->default('web')->after('order_status')->comment('web, shopify, woocommerce');
                }
                if (! Schema::hasColumn('sales_orders', 'source_order_id')) {
                    $table->string('source_order_id')->nullable()->after('source')->comment('Auto increment id of source_order (external)');
                }
                if (! Schema::hasColumn('sales_orders', 'source_order_number')) {
                    $table->string('source_order_number')->nullable()->after('source_order_id')->comment('Source order number');
                }
            });

            if (! Schema::hasTable('sales_order_errors')) {
                Schema::create('sales_order_errors', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->constrained('sales_orders')->onDelete('cascade');
                    $table->string('error_key')->nullable();
                    $table->text('error_description')->nullable();
                    $table->timestamps();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_errors');

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_order_id', 'source_order_number']);
        });
    }
};
