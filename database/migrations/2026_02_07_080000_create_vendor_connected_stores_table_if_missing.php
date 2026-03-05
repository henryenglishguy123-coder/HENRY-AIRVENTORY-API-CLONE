<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_connected_stores')) {
            Schema::create('vendor_connected_stores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->string('channel');
                $table->string('store_identifier')->comment('Unique external store identifier');
                $table->string('link')->nullable();
                $table->text('token')->nullable();
                $table->json('additional_data')->nullable();
                $table->enum('status', ['connected', 'disconnected', 'error'])->default('connected');
                $table->timestamp('last_synced_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'channel', 'store_identifier'], 'vendor_channel_store_unique');
                $table->index('status');
                $table->index('channel');
                $table->index('last_synced_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_connected_stores');
    }
};
