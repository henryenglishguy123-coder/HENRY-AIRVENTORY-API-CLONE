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
        if (Schema::hasTable('shipment_sequences')) {
            Schema::table('shipment_sequences', function (Blueprint $table) {
                if (! Schema::hasColumn('shipment_sequences', 'prefix')) {
                    $table->string('prefix')->default('SHP')->after('id');
                }
                if (! Schema::hasColumn('shipment_sequences', 'current_value')) {
                    $table->unsignedBigInteger('current_value')->default(0)->after('prefix');
                }
                if (Schema::hasColumn('shipment_sequences', 'factory_id')) {
                    $table->dropColumn('factory_id');
                }
            });
        } else {
            Schema::create('shipment_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('prefix')->default('SHP');
                $table->unsignedBigInteger('current_value')->default(0);
                $table->string('last_shipment_number')->nullable();
                $table->timestamps();

                $table->index(['prefix']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_sequences');
    }
};
