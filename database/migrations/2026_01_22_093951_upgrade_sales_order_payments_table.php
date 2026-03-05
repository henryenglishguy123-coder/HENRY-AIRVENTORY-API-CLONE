<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_payments')) {
            Schema::table('sales_order_payments', function (Blueprint $table) {

                // Payment identification
                if (! Schema::hasColumn('sales_order_payments', 'payment_method')) {
                    $table->string('payment_method', 50)
                        ->after('transaction_id')
                        ->nullable();
                }

                if (! Schema::hasColumn('sales_order_payments', 'gateway')) {
                    $table->string('gateway', 50)
                        ->after('payment_method')
                        ->nullable();
                }

                // Status & currency
                if (! Schema::hasColumn('sales_order_payments', 'payment_status')) {
                    $table->enum('payment_status', [
                        'pending',
                        'paid',
                        'failed',
                        'refunded',
                    ])->default('pending')
                        ->after('gateway');
                }

                if (! Schema::hasColumn('sales_order_payments', 'currency_code')) {
                    $table->char('currency_code', 3)
                        ->nullable()
                        ->after('payment_status');
                }

                // Refund support
                if (! Schema::hasColumn('sales_order_payments', 'refunded_amount')) {
                    $table->decimal('refunded_amount', 10, 4)
                        ->default(0.0000)
                        ->after('amount');
                }

                // Gateway snapshot
                if (! Schema::hasColumn('sales_order_payments', 'gateway_response')) {
                    $table->json('gateway_response')
                        ->nullable()
                        ->after('refunded_amount');
                }

                // Meta
                if (! Schema::hasColumn('sales_order_payments', 'paid_at')) {
                    $table->timestamp('paid_at')
                        ->nullable()
                        ->after('gateway_response');
                }

                if (! Schema::hasColumn('sales_order_payments', 'notes')) {
                    $table->text('notes')
                        ->nullable()
                        ->after('paid_at');
                }

                // Soft deletes
                if (! Schema::hasColumn('sales_order_payments', 'deleted_at')) {
                    $table->softDeletes()
                        ->after('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales_order_payments', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'gateway',
                'payment_status',
                'currency_code',
                'refunded_amount',
                'gateway_response',
                'paid_at',
                'notes',
            ]);

            $table->dropSoftDeletes();
        });
    }
};
