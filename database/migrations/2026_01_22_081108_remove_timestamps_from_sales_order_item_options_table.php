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
        if (Schema::hasTable('sales_order_item_options')) {
            Schema::table('sales_order_item_options', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_item_options', 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn('sales_order_item_options', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_item_options', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};
