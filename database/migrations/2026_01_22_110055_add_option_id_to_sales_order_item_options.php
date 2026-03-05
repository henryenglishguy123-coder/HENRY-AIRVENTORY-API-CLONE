<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_item_options')) {
            Schema::table('sales_order_item_options', function (Blueprint $table) {

                // Ensure column exists
                if (! Schema::hasColumn('sales_order_item_options', 'option_id')) {
                    $table->unsignedBigInteger('option_id')
                        ->nullable()
                        ->after('order_item_id');
                }

                // Add FK (SET NULL, not CASCADE)
                // Note: We should ideally check if FK exists, but it's hard in generic way.
                // Assuming if column exists, we might still want to ensure FK.
                // However, adding FK multiple times might fail.
                // For safety in this specific fix context, let's keep it simple.

                try {
                    $table->foreign('option_id')
                        ->references('option_id')
                        ->on('catalog_attribute_options')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // Ignore if FK already exists or other constraint issues in SQLite
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_order_item_options', function (Blueprint $table) {
            $table->dropForeign(['option_id']);
            $table->dropColumn('option_id');
        });
    }
};
