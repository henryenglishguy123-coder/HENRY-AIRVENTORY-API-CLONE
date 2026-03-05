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
        if (Schema::hasTable('vendor_design_layers')) {
            if (DB::getDriverName() !== 'sqlite') {
                // 1. Find the foreign key name dynamically
                $fk = DB::selectOne("
                    SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'vendor_design_layers'
                      AND COLUMN_NAME = 'vendor_design_id'
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                // 2. Drop the foreign key if it exists
                if ($fk) {
                    Schema::table('vendor_design_layers', function (Blueprint $table) use ($fk) {
                        $table->dropForeign($fk->CONSTRAINT_NAME);
                    });
                }
            }

            // 3. Rename the column
            if (Schema::hasColumn('vendor_design_layers', 'vendor_design_id')) {
                Schema::table('vendor_design_layers', function (Blueprint $table) {
                    $table->renameColumn('vendor_design_id', 'vendor_design_template_id');
                });
            }

            // 4. Add the new foreign key
            Schema::table('vendor_design_layers', function (Blueprint $table) {
                // Only add if not already exists (just in case of re-run partials)
                // But simpler to just add it. ensuring referencing vendor_design_templates
                if (Schema::hasTable('vendor_design_templates')) {
                    try {
                        $table->foreign('vendor_design_template_id')
                            ->references('id')
                            ->on('vendor_design_templates')
                            ->onDelete('cascade');
                    } catch (\Exception $e) {
                        // Ignore if FK already exists or other issues in SQLite
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop the new foreign key
        Schema::table('vendor_design_layers', function (Blueprint $table) {
            $table->dropForeign(['vendor_design_template_id']);
        });

        // 2. Rename back
        if (Schema::hasColumn('vendor_design_layers', 'vendor_design_template_id')) {
            Schema::table('vendor_design_layers', function (Blueprint $table) {
                $table->renameColumn('vendor_design_template_id', 'vendor_design_id');
            });
        }

        // 3. Re-add the old foreign key
        // We assume it references vendor_design_templates
        Schema::table('vendor_design_layers', function (Blueprint $table) {
            $table->foreign('vendor_design_id')
                ->references('id')
                ->on('vendor_design_templates')
                ->onDelete('cascade');
        });
    }
};
