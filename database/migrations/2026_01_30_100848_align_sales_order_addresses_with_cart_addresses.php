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
        if (Schema::hasTable('sales_order_addresses')) {
            Schema::table('sales_order_addresses', function (Blueprint $table) {
                // Rename columns
                if (Schema::hasColumn('sales_order_addresses', 'firstname')) {
                    $table->renameColumn('firstname', 'first_name');
                }
                if (Schema::hasColumn('sales_order_addresses', 'lastname')) {
                    $table->renameColumn('lastname', 'last_name');
                }
                if (Schema::hasColumn('sales_order_addresses', 'zip_code')) {
                    $table->renameColumn('zip_code', 'postal_code');
                }

                // Add new columns
                if (! Schema::hasColumn('sales_order_addresses', 'state')) {
                    $table->string('state', 150)->nullable()->after('state_id');
                }
                if (! Schema::hasColumn('sales_order_addresses', 'country')) {
                    $table->string('country', 150)->nullable()->after('country_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_addresses', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['state', 'country']);

            // Revert renames
            $table->renameColumn('first_name', 'firstname');
            $table->renameColumn('last_name', 'lastname');
            $table->renameColumn('postal_code', 'zip_code');
        });
    }
};
