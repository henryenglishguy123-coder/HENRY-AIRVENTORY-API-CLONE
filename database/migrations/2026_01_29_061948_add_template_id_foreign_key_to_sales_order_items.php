<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_items')) {
            return;
        }

        // Ensure column exists & is nullable
        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'template_id')) {
                // Skip change() for SQLite if possible to avoid issues, or catch errors
                try {
                    $table->unsignedBigInteger('template_id')->nullable()->change();
                } catch (\Exception $e) {
                    // Ignore
                }
            } else {
                $table->unsignedBigInteger('template_id')->nullable();
            }
        });

        // Add FK only if it does not already exist
        $fkExists = false;

        if (DB::getDriverName() !== 'sqlite') {
            $fkExists = ! empty(DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'sales_order_items'
                  AND COLUMN_NAME = 'template_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            "));
        }

        if (! $fkExists && Schema::hasColumn('sales_order_items', 'template_id') && Schema::hasTable('vendor_design_templates')) {
            try {
                Schema::table('sales_order_items', function (Blueprint $table) {
                    $table->foreign('template_id')
                        ->references('id')
                        ->on('vendor_design_templates')
                        ->nullOnDelete();
                });
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropForeign(['template_id']);
            });
        } catch (\Throwable $e) {
            // Foreign key might not exist or already dropped
        }

        if (Schema::hasColumn('sales_order_items', 'template_id')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->dropColumn('template_id');
            });
        }
    }
};
