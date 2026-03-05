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

        $fk = null;

        // Drop FK if it exists (MySQL only)
        if (DB::getDriverName() !== 'sqlite') {
            $fk = DB::selectOne("
                SELECT kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, rc.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.REFERENTIAL_CONSTRAINTS rc 
                  ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
                  AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = DATABASE()
                  AND kcu.TABLE_NAME = 'sales_order_items'
                  AND kcu.COLUMN_NAME = 'variation_id'
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if ($fk) {
                DB::statement(
                    "ALTER TABLE sales_order_items DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}"
                );
            }
        }

        // Rename column only
        if (
            Schema::hasColumn('sales_order_items', 'variation_id') &&
            ! Schema::hasColumn('sales_order_items', 'variant_id')
        ) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->renameColumn('variation_id', 'variant_id');
            });
        }

        // Recreate FK (MySQL only)
        if ($fk && Schema::hasColumn('sales_order_items', 'variant_id')) {
            Schema::table('sales_order_items', function (Blueprint $table) use ($fk) {
                $foreign = $table->foreign('variant_id')
                    ->references($fk->REFERENCED_COLUMN_NAME)
                    ->on($fk->REFERENCED_TABLE_NAME);

                match ($fk->DELETE_RULE) {
                    'CASCADE' => $foreign->cascadeOnDelete(),
                    'SET NULL' => $foreign->nullOnDelete(),
                    'RESTRICT', 'NO ACTION' => $foreign->restrictOnDelete(),
                    default => $foreign->restrictOnDelete(),
                };
            });
        }
    }

    public function down(): void
    {
        // Drop FK if exists on variant_id
        $fk = DB::selectOne("
            SELECT kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc 
              ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
              AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = 'sales_order_items'
              AND kcu.COLUMN_NAME = 'variant_id'
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ");

        if ($fk) {
            DB::statement(
                "ALTER TABLE sales_order_items DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}"
            );
        }

        if (
            Schema::hasColumn('sales_order_items', 'variant_id') &&
            ! Schema::hasColumn('sales_order_items', 'variation_id')
        ) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                $table->renameColumn('variant_id', 'variation_id');
            });
        }

        // Recreate FK for variation_id
        if ($fk && Schema::hasColumn('sales_order_items', 'variation_id')) {
            Schema::table('sales_order_items', function (Blueprint $table) use ($fk) {
                $foreign = $table->foreign('variation_id')
                    ->references($fk->REFERENCED_COLUMN_NAME)
                    ->on($fk->REFERENCED_TABLE_NAME);

                match ($fk->DELETE_RULE) {
                    'CASCADE' => $foreign->cascadeOnDelete(),
                    'SET NULL' => $foreign->nullOnDelete(),
                    'RESTRICT', 'NO ACTION' => $foreign->restrictOnDelete(),
                    default => $foreign->restrictOnDelete(),
                };
            });
        }
    }
};
