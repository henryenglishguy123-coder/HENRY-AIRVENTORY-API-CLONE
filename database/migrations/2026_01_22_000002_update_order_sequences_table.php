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
        if (! Schema::hasTable('order_sequences')) {
            Schema::create('order_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('prefix')->default('AIO');
                $table->unsignedBigInteger('current_value')->default(0);
                $table->timestamps();
            });
        } else {
            Schema::table('order_sequences', function (Blueprint $table) {
                if (! Schema::hasColumn('order_sequences', 'prefix')) {
                    $table->string('prefix')->default('AIO')->after('id');
                }
                if (! Schema::hasColumn('order_sequences', 'current_value')) {
                    $table->unsignedBigInteger('current_value')->default(0)->after('prefix');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_sequences');
    }
};
