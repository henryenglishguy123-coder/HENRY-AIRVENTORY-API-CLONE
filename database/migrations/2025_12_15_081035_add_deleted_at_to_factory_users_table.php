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
                if (! Schema::hasColumn('factory_users', 'deleted_at')) {
                    $table->softDeletes()->after('stripe_account_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factory_users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
