<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('carts')) {
            DB::statement("ALTER TABLE carts MODIFY status ENUM('active','abandoned','converted','imported','hold') NOT NULL DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Acquire lock to prevent race conditions during rollback
        DB::unprepared('LOCK TABLES carts WRITE');

        try {
            // Map 'hold' back to 'active'
            DB::table('carts')->where('status', 'hold')->update(['status' => 'active']);

            // Revert enum definition
            DB::statement("ALTER TABLE carts MODIFY status ENUM('active','abandoned','converted','imported') NOT NULL DEFAULT 'active'");
        } finally {
            // Always release lock
            DB::unprepared('UNLOCK TABLES');
        }
    }
};
