<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_channels', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('code', 50)->unique(); // shopify, etsy, woocommerce
            $table->string('name', 100);          // Shopify, Etsy
            $table->string('logo')->nullable();   // path to logo

            // UI content
            $table->text('description')->nullable();

            // Auth & connection type
            $table->enum('auth_type', [
                'oauth',
                'api_key',
                'basic_auth',
                'custom',
            ]);

            /**
             * Example JSON:
             * {
             *   "store_url": true,
             *   "api_key": true,
             *   "api_secret": true
             * }
             */
            $table->json('required_credentials')->nullable();

            // Control visibility on frontend
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_channels');
    }
};
