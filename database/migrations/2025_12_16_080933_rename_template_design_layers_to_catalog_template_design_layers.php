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
        if (Schema::hasTable('template_design_layers')) {
            Schema::rename('template_design_layers', 'catalog_design_template_layers');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('catalog_design_template_layers', 'template_design_layers');
    }
};
