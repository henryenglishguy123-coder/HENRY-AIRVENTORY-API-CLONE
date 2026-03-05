<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_addresses')) {
            // 1. Rename existing columns and Add new columns
            Schema::table('sales_order_addresses', function (Blueprint $table) {
                // Rename existing columns
                if (Schema::hasColumn('sales_order_addresses', 'mobile_number') && ! Schema::hasColumn('sales_order_addresses', 'phone')) {
                    $table->renameColumn('mobile_number', 'phone');
                }
                if (Schema::hasColumn('sales_order_addresses', 'email_id') && ! Schema::hasColumn('sales_order_addresses', 'email')) {
                    $table->renameColumn('email_id', 'email');
                }
                if (Schema::hasColumn('sales_order_addresses', 'address') && ! Schema::hasColumn('sales_order_addresses', 'address_line_1')) {
                    $table->renameColumn('address', 'address_line_1');
                }

                // Add new columns
                if (! Schema::hasColumn('sales_order_addresses', 'firstname')) {
                    $table->string('firstname')->nullable()->after('order_id');
                }
                if (! Schema::hasColumn('sales_order_addresses', 'lastname')) {
                    $table->string('lastname')->nullable()->after('firstname');
                }
                if (! Schema::hasColumn('sales_order_addresses', 'address_line_2')) {
                    $table->string('address_line_2')->nullable()->after('address_line_1');
                }
            });

            // 2. Data Migration: Populate firstname and lastname from recipient_name
            if (Schema::hasColumn('sales_order_addresses', 'recipient_name')) {
                DB::table('sales_order_addresses')
                    ->whereNotNull('recipient_name')
                    ->orderBy('id')
                    ->chunk(100, function ($addresses) {
                        foreach ($addresses as $address) {
                            $fullName = trim($address->recipient_name);
                            $parts = explode(' ', $fullName, 2);
                            $firstname = $parts[0] ?? '';
                            $lastname = $parts[1] ?? '';

                            DB::table('sales_order_addresses')
                                ->where('id', $address->id)
                                ->update([
                                    'firstname' => $firstname,
                                    'lastname' => $lastname,
                                ]);
                        }
                    });
            }

            // 3. Drop old combined column
            Schema::table('sales_order_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_addresses', 'recipient_name')) {
                    $table->dropColumn('recipient_name');
                }
            });
        }
    }

    public function down(): void
    {
        // 1. Restore dropped column and Revert column names
        Schema::table('sales_order_addresses', function (Blueprint $table) {
            // Revert column names
            $table->renameColumn('phone', 'mobile_number');
            $table->renameColumn('email', 'email_id');
            $table->renameColumn('address_line_1', 'address');

            // Restore dropped column
            $table->string('recipient_name')->nullable()->after('order_id');
        });

        // 2. Data Migration: Populate recipient_name from firstname and lastname
        DB::table('sales_order_addresses')
            ->orderBy('id')
            ->chunk(100, function ($addresses) {
                foreach ($addresses as $address) {
                    $firstname = $address->firstname ?? '';
                    $lastname = $address->lastname ?? '';
                    $fullName = trim($firstname.' '.$lastname);

                    if (! empty($fullName)) {
                        DB::table('sales_order_addresses')
                            ->where('id', $address->id)
                            ->update(['recipient_name' => $fullName]);
                    }
                }
            });

        // 3. Drop newly added columns
        Schema::table('sales_order_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'firstname',
                'lastname',
                'address_line_2',
            ]);
        });
    }
};
