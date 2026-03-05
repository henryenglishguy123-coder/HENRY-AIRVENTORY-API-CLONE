<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerDashboardRequest;
use App\Models\Currency\Currency;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Customer\CustomerResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        protected CustomerResolverService $customerResolverService
    ) {}

    public function index(CustomerDashboardRequest $request)
    {
        $customer = $this->customerResolverService->resolve($request);

        $account = [
            'name' => trim($customer->first_name.' '.$customer->last_name),
        ];

        $requestedCurrency = $request->input('currency');
        $defaultCurrency = Currency::getDefaultCurrency();
        $activeCurrency = $defaultCurrency;
        if ($requestedCurrency) {
            $found = Currency::query()->where('code', $requestedCurrency)->where('is_allowed', 1)->first();
            if ($found) {
                $activeCurrency = $found;
            }
        }
        $currencyCode = $activeCurrency->code;

        $dates = $this->resolveDateRange(
            $request->input('period', '30_days'),
            $request->input('start_date'),
            $request->input('end_date')
        );

        [$currentStart, $currentEnd] = [$dates['start'], $dates['end']];

        $days = $currentStart->diffInDays($currentEnd) + 1;
        $prevEnd = $currentStart->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        $baseQuery = $this->baseOrderQuery($customer->id, $request);

        $currentStats = (clone $baseQuery)
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(grand_total_inc_margin), 0) as revenue,
                COALESCE(SUM(grand_total_inc_margin - grand_total), 0) as profit
            ')
            ->first();

        $previousRevenue = (clone $baseQuery)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('grand_total_inc_margin');

        $growthRate = $this->calculateGrowthRate(
            (float) $currentStats->revenue,
            (float) $previousRevenue
        );

        $graphData = (clone $baseQuery)
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->format('d M Y'),
                'orders_count' => (int) $row->orders,
            ]);

        $recentOrders = (clone $baseQuery)
            ->with(['shippingAddress', 'billingAddress'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) use ($currencyCode) {
                $address = $order->shippingAddress ?? $order->billingAddress;

                return [
                    'order_number' => $order->order_number,
                    'recipient_name' => $address
                        ? trim($address->first_name.' '.$address->last_name)
                        : 'N/A',
                    'order_date' => $order->created_at->format('d M Y'),
                    'price' => format_price($order->grand_total_inc_margin, $currencyCode),
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                ];
            });

        return response()->json([
            'account' => $account,
            'currency' => [
                'code' => $currencyCode,
                'symbol' => $activeCurrency->symbol,
            ],
            'stats' => [
                'total_orders' => (int) $currentStats->total_orders,
                'total_revenue' => format_price($currentStats->revenue, $currencyCode),
                'total_profit' => format_price($currentStats->profit, $currencyCode),
                'growth_rate' => round($growthRate, 2),
            ],
            'graph_data' => $graphData,
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * Build base order query with filters
     */
    private function baseOrderQuery(int $customerId, Request $request)
    {
        return SalesOrder::query()
            ->where('customer_id', $customerId)
            ->when($request->filled('payment_status'),
                fn ($q) => $q->where('payment_status', $request->payment_status)
            )
            ->when($request->filled('order_status'),
                fn ($q) => $q->where('order_status', $request->order_status)
            );
    }

    /**
     * Resolve dashboard date range
     */
    private function resolveDateRange(string $period, ?string $startDate, ?string $endDate): array
    {
        $end = Carbon::now();
        $start = Carbon::now()->subDays(29);

        match ($period) {
            '7_days' => $start = Carbon::now()->subDays(6),
            '30_days' => $start = Carbon::now()->subDays(29),
            '3_months' => $start = Carbon::now()->subMonths(3),
            'custom' => $startDate && $endDate
                ? [$start = Carbon::parse($startDate), $end = Carbon::parse($endDate)]
                : null,
            default => null,
        };

        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ];
    }

    /**
     * Revenue growth calculation
     */
    private function calculateGrowthRate(float $current, float $previous): float
    {
        if ($previous > 0) {
            return (($current - $previous) / $previous) * 100;
        }

        return $current > 0 ? 100 : 0;
    }
}
