<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Drop existing tables
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_zones');
        Schema::dropIfExists('taxes');

        /*
        |--------------------------------------------------------------------------
        | Create taxes table (Tax master)
        |--------------------------------------------------------------------------
        */
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // GST, VAT
            $table->string('name');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Create tax_zones table (Geographical zones)
        |--------------------------------------------------------------------------
        */
        Schema::create('tax_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('country_id');
            $table->string('state_code')->nullable();
            $table->string('postal_code_start')->nullable();
            $table->string('postal_code_end')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->index(['country_id', 'state_code']);
        });

        /*
        |--------------------------------------------------------------------------
        | Create tax_rules table (Rate + priority)
        |--------------------------------------------------------------------------
        */
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tax_id');
            $table->unsignedBigInteger('tax_zone_id');
            $table->decimal('rate', 8, 2);
            $table->integer('priority')->default(1);
            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->foreign('tax_id')->references('id')->on('taxes')->cascadeOnDelete();
            $table->foreign('tax_zone_id')->references('id')->on('tax_zones')->cascadeOnDelete();

            $table->index(['tax_zone_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_zones');
        Schema::dropIfExists('taxes');
    }
};
