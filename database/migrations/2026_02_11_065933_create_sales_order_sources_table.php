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
        Schema::dropIfExists('sales_orders_sources');
        Schema::create('sales_order_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();

            $table->string('platform', 50)
                ->comment('External platform e.g. shopify, woocommerce, manual');

            $table->string('source', 255)
                ->nullable()
                ->comment('Source identifier (e.g. shop domain, store URL)');

            $table->string('source_order_id', 100)
                ->nullable()
                ->comment('External order ID');

            $table->string('source_order_number', 100)
                ->nullable()
                ->comment('External order number');

            $table->timestamp('source_created_at')
                ->nullable()
                ->comment('When the order was created on the source platform');

            $table->json('payload')
                ->nullable()
                ->comment('Raw external order payload');

            $table->timestamps();

            $table->unique(['order_id', 'platform'], 'sales_order_platform_unique');
            $table->index(['platform', 'source_order_id'], 'sales_order_source_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_sources');
    }
};
