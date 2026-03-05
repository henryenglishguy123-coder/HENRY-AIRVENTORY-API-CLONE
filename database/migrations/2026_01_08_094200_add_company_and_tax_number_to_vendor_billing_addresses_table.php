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
        Schema::table('vendor_billing_addresses', function (Blueprint $table) {
            $table->string('company_name', 255)
                ->nullable()
                ->after('last_name');

            $table->string('tax_number', 100)
                ->nullable()
                ->after('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_billing_addresses', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'tax_number']);
        });
    }
};
