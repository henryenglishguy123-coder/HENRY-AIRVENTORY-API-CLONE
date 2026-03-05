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
        // 1. Create the new table
        if (! Schema::hasTable('vendor_design_template_store_images')) {
            Schema::create('vendor_design_template_store_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_design_template_store_id')
                    ->constrained('vendor_design_template_stores')
                    ->cascadeOnDelete()
                    ->name('fk_vdt_store_images_store_id'); // Shortened name
                $table->string('image_path');
                $table->enum('type', ['primary', 'sync'])->default('sync');
                $table->timestamps();
            });
        }

        // 2. Migrate existing data if table exists
        if (Schema::hasTable('vendor_design_template_stores')) {
            // Check for columns before migrating
            $hasPrimary = Schema::hasColumn('vendor_design_template_stores', 'primary_image_id');
            $hasSync = Schema::hasColumn('vendor_design_template_stores', 'sync_image_id');

            if ($hasPrimary || $hasSync) {
                // Fetch stores with images
                $stores = \Illuminate\Support\Facades\DB::table('vendor_design_template_stores')
                    ->whereNotNull('primary_image_id')
                    ->orWhereNotNull('sync_image_id')
                    ->get();

                foreach ($stores as $store) {
                    // Migrate Primary Image
                    if ($hasPrimary && $store->primary_image_id) {
                        // Assuming images table holds path, but instruction says "insert corresponding rows... linking vendor_design_template_store_id to the image id".
                        // Wait, the new table stores 'image_path'. The old columns were FKs to 'vendor_design_layer_images'.
                        // I need to join with vendor_design_layer_images (or 'images' table as per instruction hint, but FK was to vendor_design_layer_images in down())
                        // The down() says references('id')->on('vendor_design_layer_images').
                        // So I need to fetch the path from there.
                        $image = \Illuminate\Support\Facades\DB::table('vendor_design_layer_images')->where('id', $store->primary_image_id)->first();
                        if ($image && isset($image->image_path)) {
                            \Illuminate\Support\Facades\DB::table('vendor_design_template_store_images')->insert([
                                'vendor_design_template_store_id' => $store->id,
                                'image_path' => $image->image_path,
                                'type' => 'primary',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // Migrate Sync Image
                    if ($hasSync && $store->sync_image_id) {
                        $image = \Illuminate\Support\Facades\DB::table('vendor_design_layer_images')->where('id', $store->sync_image_id)->first();
                        if ($image && isset($image->image_path)) {
                            \Illuminate\Support\Facades\DB::table('vendor_design_template_store_images')->insert([
                                'vendor_design_template_store_id' => $store->id,
                                'image_path' => $image->image_path,
                                'type' => 'sync',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // 3. Drop columns safely
            Schema::table('vendor_design_template_stores', function (Blueprint $table) {
                if (Schema::hasColumn('vendor_design_template_stores', 'primary_image_id')) {
                    // Try to drop FK, ignore if not exists (names can vary)
                    // We can't easily check FK existence in migration without raw SQL or guessing names.
                    // The instruction suggests guarding dropForeign calls or surrounding in try/catch.
                    try {
                        $table->dropForeign(['primary_image_id']);
                    } catch (\Exception $e) {
                        // Ignore if FK doesn't exist
                    }
                    $table->dropColumn('primary_image_id');
                }

                if (Schema::hasColumn('vendor_design_template_stores', 'sync_image_id')) {
                    try {
                        $table->dropForeign(['sync_image_id']);
                    } catch (\Exception $e) {
                        // Ignore if FK doesn't exist
                    }
                    $table->dropColumn('sync_image_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_design_template_store_images');

        if (Schema::hasTable('vendor_design_template_stores')) {
            Schema::table('vendor_design_template_stores', function (Blueprint $table) {
                if (! Schema::hasColumn('vendor_design_template_stores', 'primary_image_id')) {
                    // Check if 'description' exists for 'after' placement
                    if (Schema::hasColumn('vendor_design_template_stores', 'description')) {
                        $table->unsignedBigInteger('primary_image_id')->nullable()->after('description');
                    } else {
                        $table->unsignedBigInteger('primary_image_id')->nullable();
                    }

                    if (Schema::hasTable('vendor_design_layer_images')) {
                        $table->foreign('primary_image_id')->references('id')->on('vendor_design_layer_images')->nullOnDelete();
                    }
                }

                if (! Schema::hasColumn('vendor_design_template_stores', 'sync_image_id')) {
                    // Check if 'primary_image_id' exists for 'after' placement (we just added it potentially)
                    // Since we are in the same closure, we can't rely on hasColumn for primary_image_id yet if it was just added in this transaction block?
                    // Actually Schema builder queues commands.
                    // Safest to just append or after 'description' if primary_image_id isn't guaranteed.
                    // Let's rely on description again or just append.
                    if (Schema::hasColumn('vendor_design_template_stores', 'description')) {
                        $table->unsignedBigInteger('sync_image_id')->nullable()->after('description');
                    } else {
                        $table->unsignedBigInteger('sync_image_id')->nullable();
                    }

                    if (Schema::hasTable('vendor_design_layer_images')) {
                        $table->foreign('sync_image_id')->references('id')->on('vendor_design_layer_images')->nullOnDelete();
                    }
                }
            });
        }
    }
};
