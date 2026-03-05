<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Drop legacy table
         */
        if (Schema::hasTable('vendor_shop_meta')) {
            Schema::drop('vendor_shop_meta');
        }

        /**
         * Rename main table if needed
         */
        if (! Schema::hasTable('vendor_connected_stores') && Schema::hasTable('vendor_shops')) {
            Schema::rename('vendor_shops', 'vendor_connected_stores');
        }

        if (! Schema::hasTable('vendor_connected_stores')) {
            return;
        }

        /**
         * Columns & renames
         */
        Schema::table('vendor_connected_stores', function (Blueprint $table) {

            if (Schema::hasColumn('vendor_connected_stores', 'shop_link')) {
                $table->renameColumn('shop_link', 'link');
            }

            if (Schema::hasColumn('vendor_connected_stores', 'access_token')) {
                $table->renameColumn('access_token', 'token');
            }

            if (Schema::hasColumn('vendor_connected_stores', 'platforms')) {
                $table->renameColumn('platforms', 'channel');
            }

            if (! Schema::hasColumn('vendor_connected_stores', 'store_identifier')) {
                $table->string('store_identifier')
                    ->after('channel')
                    ->comment('Unique external store identifier (domain, store ID, shop name)');
            }

            if (! Schema::hasColumn('vendor_connected_stores', 'additional_data')) {
                $table->json('additional_data')
                    ->nullable()
                    ->after('token')
                    ->comment('Platform-specific metadata (JSON)');
            }

            if (! Schema::hasColumn('vendor_connected_stores', 'status')) {
                $table->enum('status', ['connected', 'disconnected', 'error'])
                    ->default('connected')
                    ->after('additional_data')
                    ->comment('Connection status of the store');
            }

            if (! Schema::hasColumn('vendor_connected_stores', 'last_synced_at')) {
                $table->timestamp('last_synced_at')
                    ->nullable()
                    ->after('status')
                    ->comment('Last successful sync timestamp');
            }

            if (! Schema::hasColumn('vendor_connected_stores', 'error_message')) {
                $table->text('error_message')
                    ->nullable()
                    ->after('last_synced_at')
                    ->comment('Last sync or connection error message');
            }
        });

        /**
         * Backfill store_identifier
         */
        DB::statement("
            UPDATE vendor_connected_stores
            SET store_identifier = CONCAT(channel, '-', id)
            WHERE store_identifier IS NULL OR store_identifier = ''
        ");

        /**
         * Indexes
         */
        Schema::table('vendor_connected_stores', function (Blueprint $table) {
            $table->unique(
                ['vendor_id', 'channel', 'store_identifier'],
                'vendor_channel_store_unique'
            );

            $table->index('status', 'vendor_connected_stores_status_idx');
            $table->index('channel', 'vendor_connected_stores_channel_idx');
            $table->index('last_synced_at', 'vendor_connected_stores_last_synced_idx');
        });

        /**
         * Column comments / changes
         */
        Schema::table('vendor_connected_stores', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')
                ->comment('Vendor (owner) ID')
                ->change();

            $table->string('channel')
                ->comment('Platform channel (shopify, woo, magento, etc)')
                ->change();

            $table->string('link')
                ->nullable()
                ->comment('Store frontend or admin URL')
                ->change();

            $table->text('token')
                ->nullable()
                ->comment('Encrypted access token / credentials')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_connected_stores')) {
            return;
        }

        /**
         * Drop indexes
         */
        Schema::table('vendor_connected_stores', function (Blueprint $table) {
            $table->dropUnique('vendor_channel_store_unique');
            $table->dropIndex('vendor_connected_stores_status_idx');
            $table->dropIndex('vendor_connected_stores_channel_idx');
            $table->dropIndex('vendor_connected_stores_last_synced_idx');
        });

        /**
         * Drop new columns & rename back
         */
        Schema::table('vendor_connected_stores', function (Blueprint $table) {

            foreach ([
                'store_identifier',
                'additional_data',
                'status',
                'last_synced_at',
                'error_message',
            ] as $column) {
                if (Schema::hasColumn('vendor_connected_stores', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('vendor_connected_stores', 'link')) {
                $table->renameColumn('link', 'shop_link');
            }

            if (Schema::hasColumn('vendor_connected_stores', 'token')) {
                $table->renameColumn('token', 'access_token');
            }

            if (Schema::hasColumn('vendor_connected_stores', 'channel')) {
                $table->renameColumn('channel', 'platforms');
            }
        });

        /**
         * Rename table back
         */
        Schema::rename('vendor_connected_stores', 'vendor_shops');
    }
};
