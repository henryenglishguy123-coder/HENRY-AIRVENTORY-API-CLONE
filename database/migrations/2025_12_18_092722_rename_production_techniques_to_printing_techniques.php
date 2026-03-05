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
        if (Schema::hasTable('production_techniques')) {
            Schema::rename('production_techniques', 'printing_techniques');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('printing_techniques', 'production_techniques');
    }
};
