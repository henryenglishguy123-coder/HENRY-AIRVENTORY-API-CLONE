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
        Schema::table('vendor_design_template_to_catalog_product', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('factory_id')
                ->nullable()
                ->after('catalog_product_id');
            $table->foreign('factory_id')
                ->references('id')
                ->on('factory_users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_design_template_to_catalog_product', function (Blueprint $table) {
            $table->dropForeign(['factory_id']);
            $table->dropColumn('factory_id');
        });
    }
};
