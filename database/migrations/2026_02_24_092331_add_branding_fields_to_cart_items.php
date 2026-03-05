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
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->unsignedBigInteger('packaging_label_id')->nullable()->index();
                $table->unsignedBigInteger('hang_tag_id')->nullable()->index();

                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->foreign('packaging_label_id')->references('id')->on('vendor_design_branding')->nullOnDelete();
                    $table->foreign('hang_tag_id')->references('id')->on('vendor_design_branding')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    if (Schema::hasColumn('cart_items', 'packaging_label_id')) {
                        $table->dropForeign(['packaging_label_id']);
                    }
                    if (Schema::hasColumn('cart_items', 'hang_tag_id')) {
                        $table->dropForeign(['hang_tag_id']);
                    }
                }
                $columns = [];
                if (Schema::hasColumn('cart_items', 'packaging_label_id')) {
                    $columns[] = 'packaging_label_id';
                }
                if (Schema::hasColumn('cart_items', 'hang_tag_id')) {
                    $columns[] = 'hang_tag_id';
                }
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
