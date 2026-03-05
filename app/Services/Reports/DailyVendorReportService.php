<?php

namespace App\Services\Reports;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Models\Customer\Vendor;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Support\Carbon;

class DailyVendorReportService
{
    public function buildWindow(?Carbon $lastRunAt = null): array
    {
        $to = now();
        $from = $lastRunAt ? $lastRunAt : $to->copy()->subDay();

        return [$from, $to];
    }

    public function metricsForVendor(Vendor $vendor, Carbon $from, Carbon $to): array
    {
        // Consolidate window + overall metrics into a single query with CASE statements
        $aggregates = SalesOrder::query()
            ->where('customer_id', $vendor->id)
            ->selectRaw('
                SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as window_total_orders,
                SUM(CASE WHEN created_at >= ? AND created_at <= ? AND payment_status = ? THEN 1 ELSE 0 END) as window_paid_orders,
                SUM(CASE WHEN created_at >= ? AND created_at <= ? AND payment_status != ? AND payment_status != ? THEN 1 ELSE 0 END) as window_unpaid_orders,
                SUM(CASE WHEN created_at >= ? AND created_at <= ? AND order_status = ? THEN 1 ELSE 0 END) as window_shipped_orders,
                SUM(CASE WHEN payment_status != ? AND payment_status != ? THEN 1 ELSE 0 END) as overall_unpaid_orders
            ', [
                $from, $to,
                $from, $to, PaymentStatus::Paid->value,
                $from, $to, PaymentStatus::Paid->value, PaymentStatus::Refunded->value,
                $from, $to, OrderStatus::Shipped->value,
                PaymentStatus::Paid->value, PaymentStatus::Refunded->value,
            ])
            ->first();

        // Consolidate exception counts into a single query
        $exceptions = SalesOrder::query()
            ->where('customer_id', $vendor->id)
            ->selectRaw('
                SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as window_exceptions,
                COUNT(*) as overall_exceptions
            ', [$from, $to])
            ->whereHas('errors')
            ->first();

        return [
            'window' => [
                'from' => $from,
                'to' => $to,
            ],
            'window_metrics' => [
                'total_orders' => (int) ($aggregates->window_total_orders ?? 0),
                'paid_orders' => (int) ($aggregates->window_paid_orders ?? 0),
                'unpaid_orders' => (int) ($aggregates->window_unpaid_orders ?? 0),
                'shipped_orders' => (int) ($aggregates->window_shipped_orders ?? 0),
                'exceptions' => (int) ($exceptions->window_exceptions ?? 0),
            ],
            'overall' => [
                'unpaid_orders' => (int) ($aggregates->overall_unpaid_orders ?? 0),
                'exceptions' => (int) ($exceptions->overall_exceptions ?? 0),
            ],
        ];
    }
}
