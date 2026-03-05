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
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                // Try to drop the index if it exists.
                // For SQLite, Schema::hasIndex is not always reliable or available in older versions,
                // but catching the exception is a safe bet or using DB::getDriverName().
                // However, Laravel's schema builder might throw if index not found.

                try {
                    $table->dropUnique('cart_items_unique_item');
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }

                $table->unique(
                    ['cart_id', 'product_id', 'variant_id', 'template_id'],
                    'cart_items_unique_item'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_item');
            $table->unique(
                ['cart_id', 'product_id', 'variant_id'],
                'cart_items_unique_item'
            );
        });
    }
};
