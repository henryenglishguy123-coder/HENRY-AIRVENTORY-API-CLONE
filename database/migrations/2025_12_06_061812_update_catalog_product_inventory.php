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
        if (Schema::hasTable('catalog_product_inventory')) {
            Schema::table('catalog_product_inventory', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_product_inventory', 'min_qunatity')) {
                    $table->dropColumn('min_qunatity');
                }

                if (Schema::hasColumn('catalog_product_inventory', 'max_quantity')) {
                    $table->dropColumn('max_quantity');
                }

                if (! Schema::hasColumn('catalog_product_inventory', 'factory_id')) {
                    $table->unsignedBigInteger('factory_id')->nullable()->after('product_id');
                }
            });
        }
        Schema::dropIfExists('catalog_product_metas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_inventory', function (Blueprint $table) {
            $table->integer('min_qunatity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->dropColumn('factory_id');
        });
        Schema::create('catalog_product_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
};
