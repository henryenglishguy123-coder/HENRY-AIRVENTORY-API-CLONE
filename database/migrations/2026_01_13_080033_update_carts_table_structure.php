<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carts')) {
            return;
        }

        /**
         * Drop old vendor_user_id FK + column
         */
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'vendor_user_id')) {
                // Attempt to drop FK if not sqlite (SQLite doesn't support dropping FK by name easily)
                if (DB::getDriverName() !== 'sqlite') {
                    try {
                        $table->dropForeign('vendor_user_carts_vendor_user_id_foreign');
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
                $table->dropColumn('vendor_user_id');
            }
        });

        /**
         * Add vendor_id + FK → vendors.id
         */
        if (! Schema::hasColumn('carts', 'vendor_id')) {
            Schema::table('carts', function (Blueprint $table) {
                // Based on original raw SQL "NULL", making it nullable.
                // Original Schema code didn't have nullable(), but raw SQL did.
                $table->unsignedBigInteger('vendor_id')->nullable()->after('id');

                if (Schema::hasTable('vendors')) {
                    $table->foreign('vendor_id')
                        ->references('id')->on('vendors')
                        ->onDelete('cascade');
                }
            });
        }

        /**
         * Remove address-related columns
         */
        Schema::table('carts', function (Blueprint $table) {
            $columns = [
                'shipping_address',
                'first_name',
                'last_name',
                'email',
                'phone',
                'city',
                'state_id',
                'zip_code',
                'country_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('carts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        /**
         * Update status ENUM - Skip for SQLite
         */
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE carts
                MODIFY status
                ENUM('active','abandoned','converted','imported')
                NOT NULL
                DEFAULT 'active'
            ");
        }
    }

    public function down(): void
    {

        /**
         * Revert status ENUM
         */
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE carts
                MODIFY status
                ENUM('active','abandoned','converted')
                NOT NULL
                DEFAULT 'active'
            ");
        }

        /**
         * Drop vendor_id FK + column
         */
        Schema::table('carts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                try {
                    $table->dropForeign('carts_vendor_id_foreign');
                } catch (\Exception $e) {
                    // ignore
                }
            }
            if (Schema::hasColumn('carts', 'vendor_id')) {
                $table->dropColumn('vendor_id');
            }
        });

        /**
         * Restore vendor_user_id + original FK
         */
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'vendor_user_id')) {
                $table->unsignedBigInteger('vendor_user_id')->after('id');

                if (Schema::hasTable('vendor_users')) {
                    $table->foreign('vendor_user_id', 'vendor_user_carts_vendor_user_id_foreign')
                        ->references('id')->on('vendor_users')
                        ->onDelete('cascade');
                }
            }
        });

        /**
         * Restore address columns (structure only)
         */
        Schema::table('carts', function (Blueprint $table) {
            $table->text('shipping_address')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('city', 150)->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
        });
    }
};
