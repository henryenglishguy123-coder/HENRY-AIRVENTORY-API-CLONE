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
        Schema::table('vendor_connected_stores', function (Blueprint $table) {
            $table->timestamp('last_order_sync_at')->nullable();
            $table->index('last_order_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_connected_stores', function (Blueprint $table) {
            $table->dropIndex(['last_order_sync_at']);
            $table->dropColumn('last_order_sync_at');
        });
    }
};
