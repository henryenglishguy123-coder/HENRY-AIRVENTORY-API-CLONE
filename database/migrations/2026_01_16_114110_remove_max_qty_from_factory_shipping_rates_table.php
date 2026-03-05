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
        Schema::table('factory_shipping_rates', function (Blueprint $table) {
            if (Schema::hasColumn('factory_shipping_rates', 'max_qty')) {
                $table->dropColumn('max_qty');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factory_shipping_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('factory_shipping_rates', 'max_qty')) {
                $table->unsignedInteger('max_qty')
                    ->nullable()
                    ->after('min_qty');
            }
        });
    }
};
