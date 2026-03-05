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
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->decimal('branding_cost', 15, 4)->default(0)->after('printing_cost');
            $table->decimal('branding_cost_inc_margin', 15, 4)->default(0)->after('branding_cost');
        });

        Schema::table('sales_order_brandings', function (Blueprint $table) {
            $table->integer('qty')->default(1)->after('hang_tag_margin_price');
            $table->decimal('packaging_total', 15, 4)->default(0)->after('qty');
            $table->decimal('hang_tag_total', 15, 4)->default(0)->after('packaging_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_brandings', function (Blueprint $table) {
            $table->dropColumn(['qty', 'packaging_total', 'hang_tag_total']);
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['branding_cost', 'branding_cost_inc_margin']);
        });
    }
};
