<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasColumn = Schema::hasColumn('order_sequences', 'last_order_number');

        Schema::table('order_sequences', function (Blueprint $table) use ($hasColumn) {
            if ($hasColumn) {
                $table->string('last_order_number')->nullable()->change();
            } else {
                $table->string('last_order_number')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('order_sequences', 'last_order_number')) {
            // Revert to not nullable
            // Use transaction to prevent race condition
            DB::transaction(function () {
                // Lock the table (or relevant rows) to ensure no new NULLs are inserted
                // Note: DB::statement with raw SQL might be needed for explicit locking if not implicitly handled by transaction isolation level.
                // However, doing update then alter within transaction is generally safer than separate calls.
                // For explicit locking in MySQL: LOCK TABLES order_sequences WRITE; ... UNLOCK TABLES;
                // But Laravel's DB::transaction might suffice if we update first.
                // To be extra safe with "acquire a write lock" as requested:
                DB::statement('LOCK TABLES order_sequences WRITE');

                try {
                    // First set default for existing nulls
                    DB::table('order_sequences')->whereNull('last_order_number')->update(['last_order_number' => '0']);

                    Schema::table('order_sequences', function (Blueprint $table) {
                        $table->string('last_order_number')->nullable(false)->change();
                    });
                } finally {
                    DB::statement('UNLOCK TABLES');
                }
            });
        }
    }
};
