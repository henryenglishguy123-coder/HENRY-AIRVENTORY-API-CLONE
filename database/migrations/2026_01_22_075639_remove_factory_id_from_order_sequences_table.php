<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_sequences', 'factory_id')) {
            return;
        }

        // Drop foreign key ONLY if it exists
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order_sequences'
              AND COLUMN_NAME = 'factory_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        foreach ($foreignKeys as $fk) {
            DB::statement(
                "ALTER TABLE order_sequences DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}"
            );
        }

        Schema::table('order_sequences', function (Blueprint $table) {
            // Drop index if exists
            try {
                $table->dropIndex(['factory_id']);
            } catch (\Throwable $e) {
            }

            // Drop column
            $table->dropColumn('factory_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_sequences', 'factory_id')) {
            return;
        }

        Schema::table('order_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('factory_id')->nullable();

            $table->foreign('factory_id')
                ->references('id')
                ->on('factories')
                ->onDelete('cascade');

            $table->index('factory_id');
        });
    }
};
