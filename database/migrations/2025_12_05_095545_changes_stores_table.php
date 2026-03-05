<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop column
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'is_enable_2fa')) {
                $table->dropColumn('is_enable_2fa');
            }
        });

        // FULL REORDER to match screenshot
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                ALTER TABLE `stores`
                    MODIFY COLUMN `deleted_at` timestamp NULL AFTER `social_links`,
                    MODIFY COLUMN `created_at` timestamp NULL AFTER `deleted_at`,
                    MODIFY COLUMN `updated_at` timestamp NULL AFTER `created_at`,
                    MODIFY COLUMN `timezone` varchar(50) NULL AFTER `updated_at`,
                    MODIFY COLUMN `icon` varchar(150) NULL AFTER `timezone`,
                    MODIFY COLUMN `favicon` varchar(150) NULL AFTER `icon`
            ');
        }
    }

    public function down(): void
    {
        // Re-add the column
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'is_enable_2fa')) {
                $table->tinyInteger('is_enable_2fa')
                    ->default(0)
                    ->after('updated_at');  // temporary placement
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                ALTER TABLE `stores`
                    MODIFY COLUMN `deleted_at` timestamp NULL AFTER `social_links`,
                    MODIFY COLUMN `created_at` timestamp NULL AFTER `deleted_at`,
                    MODIFY COLUMN `updated_at` timestamp NULL AFTER `created_at`,
                    MODIFY COLUMN `is_enable_2fa` tinyint(1) NOT NULL DEFAULT 0 AFTER `updated_at`,
                    MODIFY COLUMN `timezone` varchar(50) NULL AFTER `is_enable_2fa`,
                    MODIFY COLUMN `icon` varchar(150) NULL AFTER `timezone`,
                    MODIFY COLUMN `favicon` varchar(150) NULL AFTER `icon`
            ');
        }
    }
};
