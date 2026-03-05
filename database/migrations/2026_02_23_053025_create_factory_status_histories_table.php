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
        Schema::create('factory_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained('factory_users')->onDelete('cascade');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('status_type'); // account_status, account_verified, email_verification
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['factory_id', 'status_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_status_histories');
    }
};
