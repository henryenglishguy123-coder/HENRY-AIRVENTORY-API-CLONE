<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_items')) {
            Schema::table('sales_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_items', 'printing_description')) {
                    $table->renameColumn('printing_description', 'printing_description_old');
                }
            });

            Schema::table('sales_order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_order_items', 'printing_description')) {
                    $table->json('printing_description')->nullable()->after('margin_price');
                }
            });

            DB::table('sales_order_items')->orderBy('id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    if (isset($row->printing_description_old) && ! empty($row->printing_description_old)) {
                        // Try to decode to see if it's already JSON, if not, encode it as a simple string value or structure
                        // Assuming it was a simple string description.
                        // We wrap it in a JSON structure or just json_encode the string.
                        // If we want it to be a JSON string: json_encode($str)
                        DB::table('sales_order_items')
                            ->where('id', $row->id)
                            ->update(['printing_description' => json_encode(['description' => $row->printing_description_old])]);
                    }
                }
            });

            Schema::table('sales_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_items', 'printing_description_old')) {
                    $table->dropColumn('printing_description_old');
                }
            });
        }
    }

    public function down(): void
    {
        // 1. Add temp column
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->text('printing_description_temp')->nullable();
        });

        // 2. Copy JSON to temp as string
        // We need to extract the description if we wrapped it, or just dump the JSON string.
        // If we wrapped it as ['description' => ...], we should try to extract it.
        // We extract the 'description' field from the JSON.
        DB::table('sales_order_items')->update([
            'printing_description_temp' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(printing_description, '$.description'))"),
        ]);

        // 3. Drop JSON column
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('printing_description');
        });

        // 4. Recreate as TEXT (per instruction)
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->text('printing_description')->nullable()->after('margin_price');
        });

        // Populate from temp
        DB::table('sales_order_items')->update([
            'printing_description' => DB::raw('printing_description_temp'),
        ]);

        // 5. Drop temp
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('printing_description_temp');
        });
    }
};
