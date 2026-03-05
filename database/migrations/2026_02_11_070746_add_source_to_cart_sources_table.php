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
            $table->string('source', 255)
                ->nullable()
                ->after('platform')
                ->comment('Source identifier (e.g. shop domain, store URL)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_sources', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
