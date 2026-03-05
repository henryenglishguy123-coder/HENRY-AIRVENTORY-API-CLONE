<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_shipping_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->cascadeOnDelete();

            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();

            $table->unsignedMediumInteger('country_id');
            $table->unsignedMediumInteger('state_id')->nullable();

            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);

            $table->softDeletes();
            $table->timestamps();

            // indexes
            $table->index(['vendor_id', 'is_default']);
            $table->index(['country_id']);
            $table->index(['state_id']);
        });

        // manually add foreign keys (because constrained() can't be used with mediumint)
        Schema::table('vendor_shipping_addresses', function (Blueprint $table) {
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->restrictOnDelete()->restrictOnUpdate();

            $table->foreign('state_id')
                ->references('id')->on('states')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        // --------------------------
        // BILLING TABLE
        // --------------------------
        Schema::create('vendor_billing_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->cascadeOnDelete();

            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();

            $table->unsignedMediumInteger('country_id');
            $table->unsignedMediumInteger('state_id')->nullable();

            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['vendor_id', 'is_default']);
            $table->index(['country_id']);
            $table->index(['state_id']);
        });

        Schema::table('vendor_billing_addresses', function (Blueprint $table) {
            $table->foreign('country_id')
                ->references('id')->on('countries')
                ->restrictOnDelete()->restrictOnUpdate();

            $table->foreign('state_id')
                ->references('id')->on('states')
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_billing_addresses');
        Schema::dropIfExists('vendor_shipping_addresses');
    }
};
