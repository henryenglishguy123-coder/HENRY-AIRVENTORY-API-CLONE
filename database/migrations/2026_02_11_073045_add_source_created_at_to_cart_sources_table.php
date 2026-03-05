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
        Schema::table('cart_sources', function (Blueprint $table) {
            $table->timestamp('source_created_at')
                ->nullable()
                ->after('source_order_number')
                ->comment('When the order was created on the source platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_sources', function (Blueprint $table) {
            $table->dropColumn('source_created_at');
        });
    }
};
