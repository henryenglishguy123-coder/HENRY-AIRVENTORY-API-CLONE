<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            $table->unique(
                ['vendor_connected_store_id', 'external_product_id'],
                'vendor_store_external_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vendor_design_template_stores', function (Blueprint $table) {
            $table->dropUnique('vendor_store_external_product_unique');
        });
    }
};
