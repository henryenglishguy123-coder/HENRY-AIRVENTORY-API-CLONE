<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_totals', function (Blueprint $table) {

            // Rename existing columns
            if (Schema::hasColumn('cart_totals', 'tax_total')) {
                $table->renameColumn('tax_total', 'subtotal_tax');
            }

            if (Schema::hasColumn('cart_totals', 'shipping_total')) {
                $table->renameColumn('shipping_total', 'shipping_amount');
            }
        });

        Schema::table('cart_totals', function (Blueprint $table) {

            // New columns (ordered properly)
            if (! Schema::hasColumn('cart_totals', 'shipping_tax')) {
                $column = $table->decimal('shipping_tax', 12, 4)->default(0);
                if (Schema::hasColumn('cart_totals', 'shipping_amount')) {
                    $column->after('shipping_amount');
                }
            }

            if (! Schema::hasColumn('cart_totals', 'shipping_total')) {
                $column = $table->decimal('shipping_total', 12, 4)->default(0);
                if (Schema::hasColumn('cart_totals', 'shipping_tax')) {
                    $column->after('shipping_tax');
                }
            }

            if (! Schema::hasColumn('cart_totals', 'tax_total')) {
                $column = $table->decimal('tax_total', 12, 4)->default(0);
                if (Schema::hasColumn('cart_totals', 'shipping_total')) {
                    $column->after('shipping_total');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_totals', function (Blueprint $table) {

            if (Schema::hasColumn('cart_totals', 'tax_total')) {
                $table->dropColumn('tax_total');
            }

            if (Schema::hasColumn('cart_totals', 'shipping_total')) {
                $table->dropColumn('shipping_total');
            }

            if (Schema::hasColumn('cart_totals', 'shipping_tax')) {
                $table->dropColumn('shipping_tax');
            }

            // Revert renamed columns
            if (Schema::hasColumn('cart_totals', 'subtotal_tax')) {
                $table->renameColumn('subtotal_tax', 'tax_total');
            }

            if (Schema::hasColumn('cart_totals', 'shipping_amount')) {
                $table->renameColumn('shipping_amount', 'shipping_total');
            }
        });
    }
};
