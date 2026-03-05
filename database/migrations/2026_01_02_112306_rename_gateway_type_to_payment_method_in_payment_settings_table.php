<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_settings')) {
            Schema::table('payment_settings', function (Blueprint $table) {
                if (Schema::hasColumn('payment_settings', 'gateway_type')) {
                    $table->renameColumn('gateway_type', 'payment_method');
                }
            });
            Schema::table('payment_settings', function (Blueprint $table) {
                if (Schema::hasColumn('payment_settings', 'payment_method')) {
                    $table
                        ->string('payment_method', 255)
                        ->after('title')
                        ->change();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_settings')) {
            Schema::table('payment_settings', function (Blueprint $table) {
                if (Schema::hasColumn('payment_settings', 'payment_method')) {
                    $table
                        ->string('payment_method', 255)
                        ->after('id')
                        ->change();
                }
            });
            Schema::table('payment_settings', function (Blueprint $table) {
                if (Schema::hasColumn('payment_settings', 'payment_method')) {
                    $table->renameColumn('payment_method', 'gateway_type');
                }
            });
        }
    }
};
