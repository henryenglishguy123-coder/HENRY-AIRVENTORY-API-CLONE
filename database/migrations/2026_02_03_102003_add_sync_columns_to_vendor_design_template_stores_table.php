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
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            $table->enum('sync_status', ['pending', 'syncing', 'synced', 'failed'])->default('pending')->after('vendor_connected_store_id');
            $table->string('external_product_id', 255)->nullable()->after('sync_status');
            $table->text('sync_error')->nullable()->after('external_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'external_product_id', 'sync_error']);
        });
    }
};
