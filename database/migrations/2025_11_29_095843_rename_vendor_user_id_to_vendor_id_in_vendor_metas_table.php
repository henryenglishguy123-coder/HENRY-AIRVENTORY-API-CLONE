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
        Schema::table('vendor_metas', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_metas', 'vendor_user_id')) {
                $table->renameColumn('vendor_user_id', 'vendor_id');
            }
            if (Schema::hasColumn('vendor_metas', 'type')) {
                $table->dropColumn('type');
            }
        });
        Schema::table('vendor_metas', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_metas', 'vendor_id')) {
                $table->foreign('vendor_id')
                    ->references('id')
                    ->on('vendors')
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_metas', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_metas', 'vendor_id')) {
                $table->renameColumn('vendor_id', 'vendor_user_id');
            }
            if (! Schema::hasColumn('vendor_metas', 'type')) {
                $table->string('type')->nullable();
            }
        });
        Schema::table('vendor_metas', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_metas', 'vendor_user_id')) {
                $table->foreign('vendor_user_id')
                    ->references('id')
                    ->on('vendors')
                    ->onDelete('cascade');
            }
        });
    }
};
