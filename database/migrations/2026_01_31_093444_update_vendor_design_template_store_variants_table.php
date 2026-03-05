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
        // Split dropColumn and addColumn for SQLite compatibility
        Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->dropColumn('price');
        });

        Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->decimal('markup', 10, 2)->nullable()->comment('Specific markup percentage');
            $table->string('markup_type')->default('percentage')->comment('percentage or fixed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Split addColumn and dropColumn for SQLite compatibility
        Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
        });

        Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
            $table->dropColumn(['markup', 'markup_type']);
        });
    }
};
