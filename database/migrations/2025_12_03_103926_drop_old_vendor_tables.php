<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_addresses')) {
            Schema::drop('vendor_addresses');
        }

        if (Schema::hasTable('vendor_businesses')) {
            Schema::drop('vendor_businesses');
        }
    }

    public function down(): void {}
};
