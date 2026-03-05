<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('vendor_user_carts') && ! Schema::hasTable('carts')) {
            Schema::rename('vendor_user_carts', 'carts');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('carts') && ! Schema::hasTable('vendor_user_carts')) {
            Schema::rename('carts', 'vendor_user_carts');
        }
    }
};
