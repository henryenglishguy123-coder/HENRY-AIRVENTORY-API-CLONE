<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_payments')) {
            return;
        }

        /*
         |--------------------------------------------------------------------------
         | 1. Drop foreign key dynamically (if exists)
         |--------------------------------------------------------------------------
         */
        if (DB::getDriverName() !== 'sqlite') {
            $constraint = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'sales_order_payments'
                  AND COLUMN_NAME = 'vendor_user_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");

            if ($constraint?->CONSTRAINT_NAME) {
                DB::statement("
                    ALTER TABLE sales_order_payments
                    DROP FOREIGN KEY {$constraint->CONSTRAINT_NAME}
                ");
            }
        }

        /*
         |--------------------------------------------------------------------------
         | 2. Rename column
         |--------------------------------------------------------------------------
         */
        if (Schema::hasColumn('sales_order_payments', 'vendor_user_id')) {
            Schema::table('sales_order_payments', function (Blueprint $table) {
                $table->renameColumn('vendor_user_id', 'vendor_id');
            });
        }

        /*
         |--------------------------------------------------------------------------
         | 3. Add new foreign key
         |--------------------------------------------------------------------------
         */
        Schema::table('sales_order_payments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_payments', 'vendor_id')) {
                $table->foreign('vendor_id')
                    ->references('id')
                    ->on('vendors')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        /*
         |--------------------------------------------------------------------------
         | Drop new FK dynamically
         |--------------------------------------------------------------------------
         */
        $constraint = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'sales_order_payments'
              AND COLUMN_NAME = 'vendor_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($constraint?->CONSTRAINT_NAME) {
            DB::statement("
                ALTER TABLE sales_order_payments
                DROP FOREIGN KEY {$constraint->CONSTRAINT_NAME}
            ");
        }

        /*
         |--------------------------------------------------------------------------
         | Rename column back
         |--------------------------------------------------------------------------
         */
        if (Schema::hasColumn('sales_order_payments', 'vendor_id')) {
            Schema::table('sales_order_payments', function (Blueprint $table) {
                $table->renameColumn('vendor_id', 'vendor_user_id');
            });
        }

        /*
         |--------------------------------------------------------------------------
         | Restore original FK (best effort)
         |--------------------------------------------------------------------------
         */
        Schema::table('sales_order_payments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_payments', 'vendor_user_id')) {
                if (Schema::hasTable('vendor_users')) {
                    $table->foreign('vendor_user_id')
                        ->references('id')
                        ->on('vendor_users')
                        ->cascadeOnDelete();
                } else {
                    // Cannot restore FK: vendor_users table is missing
                }
            }
        });
    }
};
