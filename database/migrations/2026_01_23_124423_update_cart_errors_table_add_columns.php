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
        Schema::table('cart_errors', function (Blueprint $table) {
            // Drop FK first (standard naming: table_column_foreign)
            $table->dropForeign(['cart_id']);

            // Now drop the unique index that was supporting the FK
            $table->dropUnique('cart_error_unique');

            // Add a plain index for the FK (so we can re-add FK or just for performance)
            $table->index('cart_id');

            // Re-add FK
            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->cascadeOnDelete();

            // Rename columns to match spec
            $table->renameColumn('key', 'error_code');
            $table->renameColumn('description', 'error_message');

            // Add new columns
            $table->string('sku')->nullable()->after('cart_id');
            $table->unsignedBigInteger('factory_id')->nullable()->after('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_errors', function (Blueprint $table) {
            $table->dropColumn(['sku', 'factory_id']);
            $table->renameColumn('error_code', 'key');
            $table->renameColumn('error_message', 'description');

            // Restore original state (roughly)
            $table->dropForeign(['cart_id']);
            $table->dropIndex(['cart_id']);

            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            // Note: unique index might fail if duplicates were introduced
            // $table->unique(['cart_id', 'key'], 'cart_error_unique');
        });
    }
};
