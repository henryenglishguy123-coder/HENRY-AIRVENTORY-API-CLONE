<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_items')) {
            Schema::table('sales_order_items', function (Blueprint $table) {

                // Weight
                $table->decimal('unit_weight', 10, 4)
                    ->default(0.0000)
                    ->change();

                // Pricing
                $table->decimal('factory_price', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('margin_price', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('printing_cost', 10, 4)
                    ->default(0.0000)
                    ->change();

                // Row prices
                $table->decimal('row_price', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('row_price_inc_margin', 10, 4)
                    ->default(0.0000)
                    ->change();

                // Quantity & tax
                $table->integer('qty')
                    ->unsigned()
                    ->default(1)
                    ->change();

                $table->decimal('tax_rate', 5, 2)
                    ->default(0.00)
                    ->change();

                $table->decimal('discount_amount', 10, 4)
                    ->nullable()
                    ->default(0.0000)
                    ->change();

                // Subtotals
                $table->decimal('subtotal', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('subtotal_tax', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('subtotal_inc_margin', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('subtotal_inc_margin_tax', 10, 4)
                    ->default(0.0000)
                    ->change();

                // Grand totals
                $table->decimal('grand_total', 10, 4)
                    ->default(0.0000)
                    ->change();

                $table->decimal('grand_total_inc_margin', 10, 4)
                    ->default(0.0000)
                    ->change();
            });
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Irreversible migration: normalize_sales_order_items_decimals');
    }
};
