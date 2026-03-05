<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only proceed if vendor_users table exists
        if (! Schema::hasTable('vendor_users') && ! Schema::hasTable('vendors')) {
            return;
        }

        // Rename table vendor_users → vendors
        if (Schema::hasTable('vendor_users')) {
            Schema::rename('vendor_users', 'vendors');
        }

        // Remove unwanted columns + add soft deletes
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'unique_code')) {
                $table->dropColumn('unique_code');
            }
            if (Schema::hasColumn('vendors', 'alt_mobile')) {
                $table->dropColumn('alt_mobile');
            }
            if (Schema::hasColumn('vendors', 'stripe_id')) {
                $table->dropColumn('stripe_id');
            }

            // remove email_verified + account_verified
            if (Schema::hasColumn('vendors', 'email_verified')) {
                $table->dropColumn('email_verified');
            }

            if (Schema::hasColumn('vendors', 'account_verified')) {
                $table->dropColumn('account_verified');
            }

            // ✅ rename google_id → social_login_id
            if (Schema::hasColumn('vendors', 'google_id')) {
                $table->renameColumn('google_id', 'social_login_id');
            }

            // soft deletes
            if (! Schema::hasColumn('vendors', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Set the correct column order (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE vendors
                MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
                MODIFY COLUMN first_name VARCHAR(100) NOT NULL AFTER id,
                MODIFY COLUMN last_name VARCHAR(100) NULL AFTER first_name,
                MODIFY COLUMN email VARCHAR(200) NOT NULL AFTER last_name,
                MODIFY COLUMN email_verified_at TIMESTAMP NULL AFTER email,
                MODIFY COLUMN mobile VARCHAR(20) NULL AFTER email_verified_at,
                MODIFY COLUMN password VARCHAR(255) NOT NULL AFTER mobile,
                MODIFY COLUMN last_login TIMESTAMP NULL AFTER password,
                MODIFY COLUMN source ENUM('web_admin','signup','google_login') NOT NULL DEFAULT 'signup' AFTER last_login,
                MODIFY COLUMN account_status TINYINT NOT NULL DEFAULT 1 COMMENT '0=disabled, 1=enabled, 2=blocked, 3=suspended' AFTER source,
                MODIFY COLUMN remember_token VARCHAR(100) NULL AFTER account_status,
                MODIFY COLUMN created_at TIMESTAMP NULL AFTER remember_token,
                MODIFY COLUMN updated_at TIMESTAMP NULL AFTER created_at,
                MODIFY COLUMN social_login_id VARCHAR(255) NULL AFTER updated_at,
                MODIFY COLUMN deleted_at TIMESTAMP NULL AFTER social_login_id
            ");
        }
    }

    public function down()
    {
        Schema::table('vendors', function (Blueprint $table) {

            // Re-add removed columns
            if (! Schema::hasColumn('vendors', 'unique_code')) {
                $table->string('unique_code')->nullable();
            }
            if (! Schema::hasColumn('vendors', 'alt_mobile')) {
                $table->string('alt_mobile', 20)->nullable();
            }
            if (! Schema::hasColumn('vendors', 'stripe_id')) {
                $table->string('stripe_id')->nullable();
            }

            // rollback email_verified
            if (! Schema::hasColumn('vendors', 'email_verified')) {
                $table->tinyInteger('email_verified')->default(0)->comment('0=not verified,1=verified');
            }

            // rollback account_verified
            if (! Schema::hasColumn('vendors', 'account_verified')) {
                $table->tinyInteger('account_verified')->default(2)->comment('0=rejected,1=verified,2=pending,3=hold,4=processing');
            }

            // ✅ rename social_login_id → google_id
            if (Schema::hasColumn('vendors', 'social_login_id')) {
                $table->renameColumn('social_login_id', 'google_id');
            }

            if (Schema::hasColumn('vendors', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        // Rename back vendors → vendor_users
        if (Schema::hasTable('vendors')) {
            Schema::rename('vendors', 'vendor_users');
        }
    }
};
