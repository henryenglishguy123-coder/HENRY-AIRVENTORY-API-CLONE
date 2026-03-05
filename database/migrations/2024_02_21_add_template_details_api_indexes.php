<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add performance indexes for template details API.
     */
    public function up(): void
    {
        // Indexes for vendor_design_template_stores
        if (Schema::hasTable('vendor_design_template_stores')) {
            Schema::table('vendor_design_template_stores', function (Blueprint $table) {
                if (! $this->indexExists('vendor_design_template_stores', 'idx_template_store_lookup')) {
                    $table->index(['vendor_design_template_id', 'vendor_connected_store_id'], 'idx_template_store_lookup');
                }
                if (! $this->indexExists('vendor_design_template_stores', 'idx_store_lookup')) {
                    $table->index(['vendor_connected_store_id'], 'idx_store_lookup');
                }
            });
        }

        // Indexes for vendor_design_template_store_images
        if (Schema::hasTable('vendor_design_template_store_images')) {
            Schema::table('vendor_design_template_store_images', function (Blueprint $table) {
                if (! $this->indexExists('vendor_design_template_store_images', 'idx_store_images_primary')) {
                    $table->index(['vendor_design_template_store_id', 'is_primary'], 'idx_store_images_primary');
                }
                if (! $this->indexExists('vendor_design_template_store_images', 'idx_store_images_lookup')) {
                    $table->index(['vendor_design_template_store_id'], 'idx_store_images_lookup');
                }
            });
        }

        // Indexes for vendor_design_template_store_variants
        if (Schema::hasTable('vendor_design_template_store_variants')) {
            Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
                if (! $this->indexExists('vendor_design_template_store_variants', 'idx_variant_lookup')) {
                    $table->index(['vendor_design_template_store_id', 'catalog_product_id'], 'idx_variant_lookup');
                }
                if (! $this->indexExists('vendor_design_template_store_variants', 'idx_product_lookup')) {
                    $table->index(['catalog_product_id'], 'idx_product_lookup');
                }
            });
        }

        // Indexes for vendor_design_layer_images
        if (Schema::hasTable('vendor_design_layer_images')) {
            Schema::table('vendor_design_layer_images', function (Blueprint $table) {
                if (! $this->indexExists('vendor_design_layer_images', 'idx_images_variant')) {
                    $table->index(['template_id', 'variant_id'], 'idx_images_variant');
                }
                if (! $this->indexExists('vendor_design_layer_images', 'idx_images_color')) {
                    $table->index(['template_id', 'color_id'], 'idx_images_color');
                }
                if (! $this->indexExists('vendor_design_layer_images', 'idx_images_layer')) {
                    $table->index(['layer_id'], 'idx_images_layer');
                }
            });
        }

        // Indexes for catalog_product_printing_prices
        if (Schema::hasTable('catalog_product_printing_prices')) {
            Schema::table('catalog_product_printing_prices', function (Blueprint $table) {
                if (! $this->indexExists('catalog_product_printing_prices', 'idx_printing_price_lookup')) {
                    $table->index(['layer_id', 'printing_technique_id'], 'idx_printing_price_lookup');
                }
                if (! $this->indexExists('catalog_product_printing_prices', 'idx_printing_product')) {
                    $table->index(['catalog_product_id'], 'idx_printing_product');
                }
            });
        }

        // Indexes for catalog_product_prices_with_margin
        if (Schema::hasTable('catalog_product_prices_with_margin')) {
            Schema::table('catalog_product_prices_with_margin', function (Blueprint $table) {
                if (! $this->indexExists('catalog_product_prices_with_margin', 'idx_price_factory')) {
                    $table->index(['catalog_product_id', 'factory_id'], 'idx_price_factory');
                }
                if (! $this->indexExists('catalog_product_prices_with_margin', 'idx_factory_price')) {
                    $table->index(['factory_id'], 'idx_factory_price');
                }
            });
        }

        // Indexes for vendor_design_templates
        if (Schema::hasTable('vendor_design_templates')) {
            Schema::table('vendor_design_templates', function (Blueprint $table) {
                if (! $this->indexExists('vendor_design_templates', 'idx_vendor_templates')) {
                    $table->index(['vendor_id', 'id'], 'idx_vendor_templates');
                }
                if (! $this->indexExists('vendor_design_templates', 'idx_catalog_template')) {
                    $table->index(['catalog_design_template_id'], 'idx_catalog_template');
                }
            });
        }

        // Indexes for vendor_design_layers
        if (Schema::hasTable('vendor_design_layers')) {
            Schema::table('vendor_design_layers', function (Blueprint $table) {
                if (! $this->indexExists('vendor_design_layers', 'idx_template_layers')) {
                    $table->index(['vendor_design_template_id'], 'idx_template_layers');
                }
                if (! $this->indexExists('vendor_design_layers', 'idx_technique_lookup')) {
                    $table->index(['technique_id'], 'idx_technique_lookup');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('vendor_design_template_stores')) {
            Schema::table('vendor_design_template_stores', function (Blueprint $table) {
                if ($this->indexExists('vendor_design_template_stores', 'idx_template_store_lookup')) {
                    $table->dropIndex('idx_template_store_lookup');
                }
                if ($this->indexExists('vendor_design_template_stores', 'idx_store_lookup')) {
                    $table->dropIndex('idx_store_lookup');
                }
            });
        }

        if (Schema::hasTable('vendor_design_template_store_images')) {
            Schema::table('vendor_design_template_store_images', function (Blueprint $table) {
                if ($this->indexExists('vendor_design_template_store_images', 'idx_store_images_primary')) {
                    $table->dropIndex('idx_store_images_primary');
                }
                if ($this->indexExists('vendor_design_template_store_images', 'idx_store_images_lookup')) {
                    $table->dropIndex('idx_store_images_lookup');
                }
            });
        }

        if (Schema::hasTable('vendor_design_template_store_variants')) {
            Schema::table('vendor_design_template_store_variants', function (Blueprint $table) {
                if ($this->indexExists('vendor_design_template_store_variants', 'idx_variant_lookup')) {
                    $table->dropIndex('idx_variant_lookup');
                }
                if ($this->indexExists('vendor_design_template_store_variants', 'idx_product_lookup')) {
                    $table->dropIndex('idx_product_lookup');
                }
            });
        }

        if (Schema::hasTable('vendor_design_layer_images')) {
            Schema::table('vendor_design_layer_images', function (Blueprint $table) {
                if ($this->indexExists('vendor_design_layer_images', 'idx_images_variant')) {
                    $table->dropIndex('idx_images_variant');
                }
                if ($this->indexExists('vendor_design_layer_images', 'idx_images_color')) {
                    $table->dropIndex('idx_images_color');
                }
                if ($this->indexExists('vendor_design_layer_images', 'idx_images_layer')) {
                    $table->dropIndex('idx_images_layer');
                }
            });
        }

        if (Schema::hasTable('catalog_product_printing_prices')) {
            Schema::table('catalog_product_printing_prices', function (Blueprint $table) {
                if ($this->indexExists('catalog_product_printing_prices', 'idx_printing_price_lookup')) {
                    $table->dropIndex('idx_printing_price_lookup');
                }
                if ($this->indexExists('catalog_product_printing_prices', 'idx_printing_product')) {
                    $table->dropIndex('idx_printing_product');
                }
            });
        }

        if (Schema::hasTable('catalog_product_prices_with_margin')) {
            Schema::table('catalog_product_prices_with_margin', function (Blueprint $table) {
                if ($this->indexExists('catalog_product_prices_with_margin', 'idx_price_factory')) {
                    $table->dropIndex('idx_price_factory');
                }
                if ($this->indexExists('catalog_product_prices_with_margin', 'idx_factory_price')) {
                    $table->dropIndex('idx_factory_price');
                }
            });
        }

        if (Schema::hasTable('vendor_design_templates')) {
            Schema::table('vendor_design_templates', function (Blueprint $table) {
                if ($this->indexExists('vendor_design_templates', 'idx_vendor_templates')) {
                    $table->dropIndex('idx_vendor_templates');
                }
                if ($this->indexExists('vendor_design_templates', 'idx_catalog_template')) {
                    $table->dropIndex('idx_catalog_template');
                }
            });
        }

        if (Schema::hasTable('vendor_design_layers')) {
            Schema::table('vendor_design_layers', function (Blueprint $table) {
                if ($this->indexExists('vendor_design_layers', 'idx_template_layers')) {
                    $table->dropIndex('idx_template_layers');
                }
                if ($this->indexExists('vendor_design_layers', 'idx_technique_lookup')) {
                    $table->dropIndex('idx_technique_lookup');
                }
            });
        }
    }

    /**
     * Check if an index exists on a table.
     * Uses allowlist to prevent SQL injection via table name.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        // For SQLite, we might not have 'SHOW INDEX', so we return false
        // and let Schema::table handles the 'index already exists' if possible,
        // or we just skip if on SQLite since it's mostly for tests.
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        // Restrict to known tables used in this migration to avoid SQL injection via table name.
        $allowedTables = [
            'vendor_design_template_stores',
            'vendor_design_template_store_images',
            'vendor_design_template_store_variants',
            'vendor_design_layer_images',
            'catalog_product_printing_prices',
            'catalog_product_prices_with_margin',
            'vendor_design_templates',
            'vendor_design_layers',
        ];

        if (! in_array($table, $allowedTables, true)) {
            throw new \InvalidArgumentException("Unexpected table name: {$table}");
        }

        // Safe identifier escaping for table name
        $safeName = DB::connection()->getTablePrefix().$table;
        $sql = "SHOW INDEX FROM `{$safeName}` WHERE Key_name = ?";
        $indexes = DB::select($sql, [$indexName]);

        return ! empty($indexes);
    }
};
