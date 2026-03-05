<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_orders')) {
            // Drop indexes first
            try {
                Schema::table('sales_orders', function (Blueprint $table) {
                    $table->dropIndex('sales_orders_store_id_index');
                });
            } catch (\Exception $e) {
            }

            try {
                Schema::table('sales_orders', function (Blueprint $table) {
                    $table->dropIndex('sales_orders_has_warning_index');
                });
            } catch (\Exception $e) {
            }

            try {
                Schema::table('sales_orders', function (Blueprint $table) {
                    $table->dropIndex('sales_orders_last_warning_at_index');
                });
            } catch (\Exception $e) {
            }

            // Drop columns
            Schema::table('sales_orders', function (Blueprint $table) {
                $columns = [
                    'store_id',
                    'has_warning',
                    'last_warning_at',
                    'is_myze_sync',
                    'zip_file',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('sales_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {

            // Re-add columns
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->boolean('has_warning')->default(false)->index();
            $table->timestamp('last_warning_at')->nullable()->index();
            $table->boolean('is_myze_sync')->default(false);
            $table->string('zip_file')->nullable();
        });
    }
};
