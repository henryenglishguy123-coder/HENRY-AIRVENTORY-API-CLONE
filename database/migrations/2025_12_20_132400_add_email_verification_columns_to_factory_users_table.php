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
        if (Schema::hasTable('factory_users')) {
            Schema::table('factory_users', function (Blueprint $table) {
                if (! Schema::hasColumn('factory_users', 'email_verification_code')) {
                    $table->string('email_verification_code', 10)->nullable()->after('password');
                }
                if (! Schema::hasColumn('factory_users', 'email_verification_code_expires_at')) {
                    $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code');
                }
                if (! Schema::hasColumn('factory_users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('email_verification_code_expires_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('factory_users')) {
            Schema::table('factory_users', function (Blueprint $table) {
                $table->dropColumn([
                    'email_verification_code',
                    'email_verification_code_expires_at',
                    'email_verified_at',
                ]);
            });
        }
    }
};
