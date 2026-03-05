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
        if (Schema::hasTable('vendor_wallets_transactions')) {
            Schema::table('vendor_wallets_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('vendor_wallets_transactions', 'status')) {
                    $table->string('status')->default('completed')->after('amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_wallets_transactions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
