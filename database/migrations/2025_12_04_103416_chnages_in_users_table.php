<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Drop columns only if they exist
            foreach ([
                'google2fa_secret',
                'google2fa_enable',
                'email_verified_at',
                'remember_token',
                'join_date',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Handle rename separately (best practice)
        if (Schema::hasColumn('users', 'last_loggedin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('last_loggedin', 'last_login_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Recreate previously dropped columns
            if (! Schema::hasColumn('users', 'google2fa_secret')) {
                $table->string('google2fa_secret')->nullable();
            }

            if (! Schema::hasColumn('users', 'google2fa_enable')) {
                $table->boolean('google2fa_enable')->default(false);
            }

            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }

            if (! Schema::hasColumn('users', 'join_date')) {
                $table->timestamp('join_date')->nullable();
            }
        });

        // Restore the original name
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('last_login_at', 'last_loggedin');
            });
        }
    }
};
