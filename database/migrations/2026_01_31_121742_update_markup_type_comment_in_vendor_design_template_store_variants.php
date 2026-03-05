<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create backup table to store IDs of rows that will be changed
        if (! Schema::hasTable('vendor_store_variants_markup_backup')) {
            Schema::create('vendor_store_variants_markup_backup', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
            });
        }

        // 2. Populate backup table with IDs of rows that have 'fixed' markup_type
        // We use raw SQL for performance and simplicity to avoid loading large datasets into memory
        DB::statement("
            INSERT INTO vendor_store_variants_markup_backup (id)
            SELECT id FROM vendor_design_template_store_variants
            WHERE markup_type = 'fixed'
        ");

        // 3. Update existing records from 'fixed' to 'profit'
        DB::table('vendor_design_template_store_variants')
            ->where('markup_type', 'fixed')
            ->update(['markup_type' => 'profit']);

        // 4. Update the schema comment
        Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->string('markup_type')->default('percentage')->comment('percentage or profit')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Revert schema comment
        Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->string('markup_type')->default('percentage')->comment('percentage or fixed')->change();
        });

        // 2. Revert data only for rows that were changed in up()
        if (Schema::hasTable('vendor_store_variants_markup_backup')) {
            DB::table('vendor_design_template_store_variants')
                ->whereIn('id', function ($query) {
                    $query->select('id')->from('vendor_store_variants_markup_backup');
                })
                ->update(['markup_type' => 'fixed']);

            // 3. Drop the backup table
            Schema::drop('vendor_store_variants_markup_backup');
        }
    }
};
