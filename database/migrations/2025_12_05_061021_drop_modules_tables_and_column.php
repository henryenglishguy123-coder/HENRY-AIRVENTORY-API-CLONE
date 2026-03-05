<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::dropIfExists('admin_modules');
        Schema::dropIfExists('modules');

        if (Schema::hasTable('admin_menus')) {
            Schema::table('admin_menus', function (Blueprint $table) {
                if (Schema::hasColumn('admin_menus', 'module_id')) {
                    $table->dropColumn('module_id');
                }
                if (Schema::hasColumn('admin_menus', 'deleted_at')) {
                    $table->dropColumn('deleted_at');
                }
                if (Schema::hasColumn('admin_menus', 'created_at')) {
                    $table->dropColumn('created_at');
                }
                if (Schema::hasColumn('admin_menus', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('admin_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->timestamps();
        });
        Schema::table('admin_menus', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }
};
