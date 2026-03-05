<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_orders')) {
            /*
             |--------------------------------------------------------------------------
             | Step 1: Temporarily convert ENUM to VARCHAR
             |--------------------------------------------------------------------------
             */
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('
                    ALTER TABLE sales_orders 
                    MODIFY order_status VARCHAR(50) NOT NULL
                ');
            }

            /*
             |--------------------------------------------------------------------------
             | Step 2: Normalize ALL known variants
             |--------------------------------------------------------------------------
             */
            DB::table('sales_orders')->whereIn('order_status', [
                'ready-to-ship',
                'ready to ship',
                'Ready-to-Ship',
                'READY-TO-SHIP',
            ])->update(['order_status' => 'ready_to_ship']);

            DB::table('sales_orders')->whereIn('order_status', [
                'processing ',
                ' Processing',
                'PROCESSING',
            ])->update(['order_status' => 'processing']);

            DB::table('sales_orders')->whereIn('order_status', [
                'Pending',
                'PENDING',
            ])->update(['order_status' => 'pending']);

            /*
             |--------------------------------------------------------------------------
             | Step 3: Fallback safety (anything invalid → pending)
             |--------------------------------------------------------------------------
             */
            DB::statement("
                UPDATE sales_orders
                SET order_status = 'pending'
                WHERE order_status NOT IN (
                    'pending',
                    'confirmed',
                    'processing',
                    'ready_to_ship',
                    'shipped',
                    'delivered',
                    'cancelled'
                )
            ");

            /*
             |--------------------------------------------------------------------------
             | Step 4: Convert back to ENUM (clean data now)
             |--------------------------------------------------------------------------
             */
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement("
                    ALTER TABLE sales_orders 
                    MODIFY order_status ENUM(
                        'pending',
                        'confirmed',
                        'processing',
                        'ready_to_ship',
                        'shipped',
                        'delivered',
                        'cancelled'
                    ) NOT NULL DEFAULT 'pending'
                ");
            }

            /*
             |--------------------------------------------------------------------------
             | Step 5: Soft deletes
             |--------------------------------------------------------------------------
             */
            Schema::table('sales_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_orders', 'deleted_at')) {
                    $table->softDeletes()->after('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        /*
         |--------------------------------------------------------------------------
         | Rollback ENUM → VARCHAR
         |--------------------------------------------------------------------------
         */
        DB::statement('
            ALTER TABLE sales_orders 
            MODIFY order_status VARCHAR(50) NOT NULL
        ');

        /*
         |--------------------------------------------------------------------------
         | Revert normalizations & Map to Old Schema
         |--------------------------------------------------------------------------
         */
        // Ensure standard statuses are mapped to valid lowercase tokens
        DB::table('sales_orders')->whereIn('order_status', [
            'Pending',
            'PENDING',
        ])->update(['order_status' => 'pending']);

        DB::table('sales_orders')->whereIn('order_status', [
            'processing ',
            ' Processing',
            'PROCESSING',
        ])->update(['order_status' => 'processing']);

        // Map 'ready_to_ship' (new) AND other variants -> 'ready-to-ship' (old)
        DB::table('sales_orders')->whereIn('order_status', [
            'ready_to_ship',
            'ready to ship',
            'Ready-to-Ship',
            'READY-TO-SHIP',
        ])->update(['order_status' => 'ready-to-ship']);

        /*
         |--------------------------------------------------------------------------
         | Restore old ENUM definition
         |--------------------------------------------------------------------------
         */
        DB::statement("
            ALTER TABLE sales_orders 
            MODIFY order_status ENUM(
                'pending',
                'confirmed',
                'processing',
                'ready-to-ship',
                'shipped',
                'delivered',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");

        /*
         |--------------------------------------------------------------------------
         | Drop Soft Deletes (Defensive)
         |--------------------------------------------------------------------------
         */
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
