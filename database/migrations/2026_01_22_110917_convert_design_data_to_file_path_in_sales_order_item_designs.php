<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_item_designs')) {
            // Safety check: Prevent truncation of long data
            $func = DB::getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
            $hasLongData = DB::table('sales_order_item_designs')
                ->whereNotNull('design_data')
                ->whereRaw("$func(design_data) > 255")
                ->exists();

            if ($hasLongData) {
                throw new \RuntimeException(
                    'Migration aborted: Found design_data entries exceeding 255 characters. '.
                    'Please run "php artisan sales:convert-design-data" to convert them to files first.'
                );
            }

            Schema::table('sales_order_item_designs', function (Blueprint $table) {
                // Convert from LONGTEXT → VARCHAR (file path)
                $table->string('design_data', 255)
                    ->nullable()
                    ->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_order_item_designs', function (Blueprint $table) {
            // Revert back if needed
            $table->longText('design_data')
                ->nullable()
                ->change();
        });
    }
};
