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
        Schema::create('factory_business', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('registration_number', 55)->nullable();
            $table->string('tax_vat_number', 55)->nullable();
            $table->string('registered_address');
            $table->unsignedSmallInteger('country_id')->nullable();
            $table->unsignedSmallInteger('state_id')->nullable();
            $table->string('city');
            $table->string('postal_code', 10);
            $table->string('registration_certificate')->nullable();
            $table->string('tax_certificate')->nullable();
            $table->string('import_export_certificate')->nullable();
            $table->unsignedBigInteger('factory_id');
            $table->timestamps();

            $table->foreign('factory_id')->references('id')->on('factory_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_business');
    }
};
