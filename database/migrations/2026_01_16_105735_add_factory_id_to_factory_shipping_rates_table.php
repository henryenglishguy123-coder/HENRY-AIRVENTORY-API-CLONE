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
        if (Schema::hasTable('factory_shipping_rates')) {
            Schema::table('factory_shipping_rates', function (Blueprint $table) {
                if (! Schema::hasColumn('factory_shipping_rates', 'factory_id')) {
                    $table->foreignId('factory_id')
                        ->after('id')
                        ->constrained('factory_users')
                        ->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factory_shipping_rates', function (Blueprint $table) {
            $table->dropForeign(['factory_id']);
            $table->dropColumn('factory_id');
        });
    }
};
