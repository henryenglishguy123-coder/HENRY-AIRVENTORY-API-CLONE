<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run this for MySQL as SQLite doesn't support MODIFY COLUMN and doesn't enforce ENUMs strictly in the same way
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE vendor_design_template_stores MODIFY COLUMN sync_status ENUM('pending', 'syncing', 'synced', 'failed', 'disconnected') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Warning: This might fail if there are rows with 'disconnected' status.
            // We will map 'disconnected' to 'pending' before reverting to avoid truncation error during rollback.
            DB::table('vendor_design_template_stores')
                ->where('sync_status', 'disconnected')
                ->update(['sync_status' => 'pending']);

            DB::statement("ALTER TABLE vendor_design_template_stores MODIFY COLUMN sync_status ENUM('pending', 'syncing', 'synced', 'failed') DEFAULT 'pending'");
        }
    }
};
