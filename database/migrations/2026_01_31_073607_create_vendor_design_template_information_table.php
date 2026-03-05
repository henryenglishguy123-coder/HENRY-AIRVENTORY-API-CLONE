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
        Schema::create('vendor_design_template_information', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_design_template_id');
            $table->foreign('vendor_design_template_id', 'vdt_info_vdt_id_fk')
                ->references('id')->on('vendor_design_templates')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_design_template_information');
    }
};
