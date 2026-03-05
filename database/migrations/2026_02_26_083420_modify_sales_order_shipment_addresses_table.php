<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Split into two Schema::table callbacks:
     * 1. Rename columns first so renamed columns exist before we add new ones.
     * 2. Add new columns (with after() references to the now-renamed columns).
     */
    public function up(): void
    {
        if (Schema::hasTable('sales_order_shipment_addresses')) {
            // Step 1: Renames only
            Schema::table('sales_order_shipment_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_shipment_addresses', 'recipient_name')) {
                    $table->renameColumn('recipient_name', 'first_name');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'mobile_number')) {
                    $table->renameColumn('mobile_number', 'phone');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'email_id')) {
                    $table->renameColumn('email_id', 'email');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'address')) {
                    $table->renameColumn('address', 'address_line_1');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'zip_code')) {
                    $table->renameColumn('zip_code', 'postal_code');
                }
            });

            // Step 2: Additions (referenced columns now exist after the renames above)
            Schema::table('sales_order_shipment_addresses', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_order_shipment_addresses', 'last_name')) {
                    $table->string('last_name', 255)->nullable()->after('first_name');
                }
                if (! Schema::hasColumn('sales_order_shipment_addresses', 'address_line_2')) {
                    $table->string('address_line_2', 255)->nullable()->after('address_line_1');
                }
                if (! Schema::hasColumn('sales_order_shipment_addresses', 'state')) {
                    // Only use after() if state_id column exists; otherwise just append
                    if (Schema::hasColumn('sales_order_shipment_addresses', 'state_id')) {
                        $table->string('state', 150)->nullable()->after('state_id');
                    } else {
                        $table->string('state', 150)->nullable();
                    }
                }
                if (! Schema::hasColumn('sales_order_shipment_addresses', 'country')) {
                    // Only use after() if country_id column exists; otherwise just append
                    if (Schema::hasColumn('sales_order_shipment_addresses', 'country_id')) {
                        $table->string('country', 150)->nullable()->after('country_id');
                    } else {
                        $table->string('country', 150)->nullable();
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Split into two Schema::table callbacks:
     * 1. Drop additive columns first.
     * 2. Rename columns back.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_order_shipment_addresses')) {
            // Step 1: Drop additive columns
            Schema::table('sales_order_shipment_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_shipment_addresses', 'last_name')) {
                    $table->dropColumn('last_name');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'address_line_2')) {
                    $table->dropColumn('address_line_2');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'state')) {
                    $table->dropColumn('state');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'country')) {
                    $table->dropColumn('country');
                }
            });

            // Step 2: Rename columns back
            Schema::table('sales_order_shipment_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_shipment_addresses', 'first_name')) {
                    $table->renameColumn('first_name', 'recipient_name');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'phone')) {
                    $table->renameColumn('phone', 'mobile_number');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'email')) {
                    $table->renameColumn('email', 'email_id');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'address_line_1')) {
                    $table->renameColumn('address_line_1', 'address');
                }
                if (Schema::hasColumn('sales_order_shipment_addresses', 'postal_code')) {
                    $table->renameColumn('postal_code', 'zip_code');
                }
            });
        }
    }
};
