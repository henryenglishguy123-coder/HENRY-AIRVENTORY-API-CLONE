<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('template_designs')) {
            Schema::rename('template_designs', 'catalog_design_template');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('catalog_design_template', 'template_designs');
    }
};
