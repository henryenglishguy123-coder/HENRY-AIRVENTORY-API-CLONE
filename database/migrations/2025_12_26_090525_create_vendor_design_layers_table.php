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
        Schema::create('vendor_design_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_design_template_id');
            $table->unsignedBigInteger('catalog_design_template_layer_id');
            $table->unsignedBigInteger('technique_id');
            $table->enum('type', ['image'])->default('image');
            $table->string('image_path');
            $table->decimal('scale_x', 18, 12)->default(1);
            $table->decimal('scale_y', 18, 12)->default(1);
            $table->decimal('width', 18, 12)->default(0);
            $table->decimal('height', 18, 12)->default(0);
            $table->decimal('rotation_angle', 6, 2)->default(0);
            $table->decimal('position_top', 18, 12)->default(0);
            $table->decimal('position_left', 18, 12)->default(0);
            $table->timestamps();
            $table->foreign('vendor_design_template_id')
                ->references('id')->on('vendor_design_templates')
                ->cascadeOnDelete();
            $table->foreign('technique_id')
                ->references('id')->on('printing_techniques')
                ->restrictOnDelete();
            $table->index(['vendor_design_template_id', 'technique_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_design_layers');
    }
};
