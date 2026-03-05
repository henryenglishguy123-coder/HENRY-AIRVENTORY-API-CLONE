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
        // Only proceed if the table exists
        if (Schema::hasTable('vendor_connected_stores')) {
            // Only add the column if it doesn't already exist
            if (! Schema::hasColumn('vendor_connected_stores', 'currency')) {
                Schema::table('vendor_connected_stores', function (Blueprint $table) {
                    $table->string('currency', 3)->nullable()->after('store_identifier')->comment('Store currency code (e.g., USD, EUR)');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only proceed if the table exists
        if (Schema::hasTable('vendor_connected_stores')) {
            // Only drop the column if it exists
            if (Schema::hasColumn('vendor_connected_stores', 'currency')) {
                Schema::table('vendor_connected_stores', function (Blueprint $table) {
                    $table->dropColumn('currency');
                });
            }
        }
    }
};
