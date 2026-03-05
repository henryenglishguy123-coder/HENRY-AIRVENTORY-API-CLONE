<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename table
        if (Schema::hasTable('designer_uploaded_images')) {
            Schema::rename('designer_uploaded_images', 'vendor_media_gallery');
        }

        // Modify columns
        if (Schema::hasTable('vendor_media_gallery')) {
            Schema::table('vendor_media_gallery', function (Blueprint $table) {
                if (Schema::hasColumn('vendor_media_gallery', 'user_id')) {
                    $table->renameColumn('user_id', 'vendor_id');
                }

                if (Schema::hasColumn('vendor_media_gallery', 'used_in_product_id')) {
                    $table->dropColumn('used_in_product_id');
                }

                if (Schema::hasColumn('vendor_media_gallery', 'uploaded_at')) {
                    $table->dropColumn('uploaded_at');
                }

                if (Schema::hasColumn('vendor_media_gallery', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        // Rename table back
        if (Schema::hasTable('vendor_media_gallery')) {
            Schema::rename('vendor_media_gallery', 'designer_uploaded_images');
        }

        if (Schema::hasTable('designer_uploaded_images')) {
            Schema::table('designer_uploaded_images', function (Blueprint $table) {
                if (Schema::hasColumn('designer_uploaded_images', 'vendor_id')) {
                    $table->renameColumn('vendor_id', 'user_id');
                }

                if (! Schema::hasColumn('designer_uploaded_images', 'used_in_product_id')) {
                    $table->unsignedBigInteger('used_in_product_id')->nullable();
                }

                if (! Schema::hasColumn('designer_uploaded_images', 'uploaded_at')) {
                    $table->timestamp('uploaded_at')->nullable();
                }

                if (! Schema::hasColumn('designer_uploaded_images', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }
};
