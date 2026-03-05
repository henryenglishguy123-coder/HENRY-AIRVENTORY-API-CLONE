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
        Schema::table('factory_shipping_partners', function (Blueprint $table) {
            // Add the new unique constraint on factory_id first
            // This ensures the foreign key has a supporting index before we drop the old one
            $table->unique('factory_id');

            // Drop the old composite unique constraint
            $table->dropUnique('factory_shipping_partner_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factory_shipping_partners', function (Blueprint $table) {
            // Re-add the composite unique constraint first
            $table->unique(['factory_id', 'shipping_partner_id'], 'factory_shipping_partner_unique');

            // Then drop the new unique constraint on factory_id
            $table->dropUnique(['factory_id']);
        });
    }
};
