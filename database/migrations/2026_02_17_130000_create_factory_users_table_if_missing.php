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
        if (! Schema::hasTable('factory_users')) {
            Schema::create('factory_users', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone_number')->nullable();
                $table->string('password')->nullable();
                $table->string('source')->nullable();
                $table->string('google_id')->nullable();
                $table->string('stripe_account_id')->nullable();
                $table->tinyInteger('account_status')->default(1)->comment('0=disabled, 1=enabled, 2=blocked, 3=suspended');
                $table->tinyInteger('account_verified')->default(2)->comment('0=rejected, 1=verified, 2=pending, 3=hold, 4=processing');
                $table->string('email_verification_code')->nullable();
                $table->timestamp('email_verification_code_expires_at')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('factory_metas')) {
            Schema::create('factory_metas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('factory_id'); // Assuming foreign key constraint or just bigInt
                $table->string('key')->index();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('factory_addresses')) {
            Schema::create('factory_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('factory_id');
                $table->string('type');
                $table->text('address');
                $table->string('country_id')->nullable();
                $table->string('state_id')->nullable();
                $table->string('city')->nullable();
                $table->string('postal_code')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_users');
    }
};
