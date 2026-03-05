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
        if (Schema::hasTable('sales_order_item_designs')) {
            Schema::table('sales_order_item_designs', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_item_designs', 'ai_file')) {
                    $table->dropColumn('ai_file');
                }

                if (Schema::hasColumn('sales_order_item_designs', 'eps_file')) {
                    $table->dropColumn('eps_file');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales_order_item_designs')) {
            Schema::table('sales_order_item_designs', function (Blueprint $table) {

                if (! Schema::hasColumn('sales_order_item_designs', 'ai_file')) {
                    $table->string('ai_file')->nullable()->after('pdf_file');
                }

                if (! Schema::hasColumn('sales_order_item_designs', 'eps_file')) {
                    $table->string('eps_file')->nullable()->after('ai_file');
                }
            });
        }
    }
};
