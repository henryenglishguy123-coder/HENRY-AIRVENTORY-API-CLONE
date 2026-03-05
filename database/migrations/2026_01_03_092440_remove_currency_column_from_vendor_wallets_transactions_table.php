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
        Schema::table('vendor_wallets_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_wallets_transactions', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_wallets_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_wallets_transactions', 'currency')) {
                $table->string('currency', 3)
                    ->nullable()
                    ->after('amount');
            }
        });
    }
};
