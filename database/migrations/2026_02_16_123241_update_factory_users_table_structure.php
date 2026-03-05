<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('factory_users')) {
            return;
        }

        Schema::table('factory_users', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('factory_users', 'email_verified')) {
                $table->dropColumn('email_verified');
            }
            if (Schema::hasColumn('factory_users', 'catalog_status')) {
                $table->dropColumn('catalog_status');
            }

            // Add new columns
            if (!Schema::hasColumn('factory_users', 'google_id')) {
                $table->string('google_id')->nullable()->after('password');
            }
            if (!Schema::hasColumn('factory_users', 'stripe_account_id')) {
                // If it already existed in fillable but not in DB, good to check
                // However user request implies it might be new or needs better handling
                $table->string('stripe_account_id')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('factory_users', 'source')) {
                $table->string('source')->nullable()->after('stripe_account_id');
            }

            // Update comments for account_status and account_verified
            if (Schema::hasColumn('factory_users', 'account_status')) {
                $table->tinyInteger('account_status')
                    ->default(1)
                    ->comment('0=disabled, 1=enabled, 2=blocked, 3=suspended')
                    ->change();
            }

            if (Schema::hasColumn('factory_users', 'account_verified')) {
                $table->tinyInteger('account_verified')
                    ->default(2) // Defaulting to pending (2) seems safer than rejected (0) or verified (1) for new users
                    ->comment('0=rejected, 1=verified, 2=pending, 3=hold, 4=processing')
                    ->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('factory_users', function (Blueprint $table) {
            if (!Schema::hasColumn('factory_users', 'email_verified')) {
                $table->integer('email_verified')->default(0);
            }
            if (!Schema::hasColumn('factory_users', 'catalog_status')) {
                $table->integer('catalog_status')->default(0);
            }
        });

        Schema::table('factory_users', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['google_id', 'stripe_account_id', 'source'] as $column) {
                if (Schema::hasColumn('factory_users', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        // Note: Reverting account_status and account_verified to original 
        // type/default/comment would require knowing their previous state
    }
};
