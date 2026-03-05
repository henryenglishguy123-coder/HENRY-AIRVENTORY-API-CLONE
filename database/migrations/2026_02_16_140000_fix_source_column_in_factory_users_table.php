<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('factory_users')) {
            return;
        }

        Schema::table('factory_users', function (Blueprint $table) {
            // Modify the source column to be a string (VARCHAR 255) nullable
            // change() ensures we modify the existing column
            if (Schema::hasColumn('factory_users', 'source')) {
                $table->string('source')->nullable()->change();
            } else {
                $table->string('source')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Best-effort reverse operation: make the "source" column non-nullable again.
        // First, ensure there are no NULL values that would violate the NOT NULL constraint.
        if (Schema::hasColumn('factory_users', 'source')) {
            DB::table('factory_users')
                ->whereNull('source')
                ->update(['source' => 'admin']); // Default to 'admin' or empty string if null
            
            Schema::table('factory_users', function (Blueprint $table) {
                // Revert to non-nullable string
                $table->string('source')->nullable(false)->change();
            });
        }
    }
};
