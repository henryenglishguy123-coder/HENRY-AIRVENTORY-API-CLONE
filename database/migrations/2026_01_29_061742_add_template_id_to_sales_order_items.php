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
                if (! Schema::hasColumn('sales_order_items', 'template_id')) {
                    $table->unsignedBigInteger('template_id')
                        ->nullable()
                        ->after('variant_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'template_id')) {
                $table->dropColumn('template_id');
            }
        });
    }
};
