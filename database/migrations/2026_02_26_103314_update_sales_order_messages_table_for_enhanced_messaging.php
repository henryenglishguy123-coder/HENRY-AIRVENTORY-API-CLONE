<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists
        if (! Schema::hasTable('sales_order_messages')) {
            Schema::create('sales_order_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_order_id');
                $table->unsignedBigInteger('sender_id');
                $table->enum('sender_role', ['customer', 'factory', 'admin']);
                $table->text('message');
                $table->json('attachments')->nullable();
                $table->enum('message_type', ['text', 'sample_sent', 'feedback', 'revision_request', 'approval', 'general'])->default('general');
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            });

            return;
        }

        // Add attachments first — message_type uses ->after('attachments'), so this must come first
        if (! Schema::hasColumn('sales_order_messages', 'attachments')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->json('attachments')->nullable()->after('message');
            });
        }

        if (! Schema::hasColumn('sales_order_messages', 'message_type')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->enum('message_type', ['text', 'sample_sent', 'feedback', 'revision_request', 'approval', 'general'])->default('general')->after('attachments');
            });
        }

        if (! Schema::hasColumn('sales_order_messages', 'sender_role')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                // Add as nullable first to avoid failure on existing rows, or provide default
                $table->enum('sender_role', ['customer', 'factory', 'admin'])->nullable()->after('sender_id');
            });

            // Backfill sender_role if sender_type existed
            if (Schema::hasColumn('sales_order_messages', 'sender_type')) {
                DB::table('sales_order_messages')->where('sender_role', null)->update([
                    'sender_role' => DB::raw('sender_type'),
                ]);
            }
        }

        // Drop the old type column if it exists
        if (Schema::hasColumn('sales_order_messages', 'type')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        // Backfill NULL messages to empty string before setting NOT NULL
        DB::table('sales_order_messages')->whereNull('message')->update(['message' => '']);

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sales_order_messages MODIFY message TEXT NOT NULL');
            DB::statement('ALTER TABLE sales_order_messages MODIFY sender_role ENUM("customer", "factory", "admin") NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sales_order_messages ALTER COLUMN message SET NOT NULL');
            DB::statement('ALTER TABLE sales_order_messages ALTER COLUMN sender_role SET NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite requires recreating the table to add NOT NULL constraint to existing columns
            // This is a simplified approach using Laravel\'s schema builder
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->string('message')->nullable(false)->change();
                $table->string('sender_role')->nullable(false)->change();
            });
        }

        // Drop the legacy sender_type column that was replaced by sender_role.
        // sender_type is NOT NULL without a default and blocks all new inserts.
        if (Schema::hasColumn('sales_order_messages', 'sender_type')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->dropColumn('sender_type');
            });
        }

        // Add indices if they don't exist (wrap in try/catch to handle already-existing indexes)
        try {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->index(['sales_order_id', 'created_at'], 'idx_sales_order_created_at');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Index already exists — safe to ignore
        }
        try {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->index(['sender_id', 'sender_role'], 'idx_sender_role');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Index already exists — safe to ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the legacy sender_type column if it was dropped in up()
        if (! Schema::hasColumn('sales_order_messages', 'sender_type')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                // Use a valid enum value as default (original column type was enum-like role)
                $table->string('sender_type')->nullable()->after('sender_id');
            });
        }

        // Restore the old type column only if it doesn't exist yet
        if (! Schema::hasColumn('sales_order_messages', 'type')) {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                // 'text' was not a valid enum value — use first valid value as default
                $table->enum('type', ['sample_sent', 'feedback', 'revision', 'approval'])
                    ->default('sample_sent')
                    ->nullable()
                    ->after('message_type');
            });
        }

        // Drop indices
        try {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->dropIndex('idx_sales_order_created_at');
            });
        } catch (\Exception $e) {
            // ignore
        }

        try {
            Schema::table('sales_order_messages', function (Blueprint $table) {
                $table->dropIndex('idx_sender_role');
            });
        } catch (\Exception $e) {
            // ignore
        }

        // Drop columns added by up() — only if they exist
        $toDrop = [];
        foreach (['message_type', 'attachments', 'sender_role'] as $col) {
            if (Schema::hasColumn('sales_order_messages', $col)) {
                $toDrop[] = $col;
            }
        }
        if (! empty($toDrop)) {
            Schema::table('sales_order_messages', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};
