<?php

namespace App\Http\Controllers\Api\V1\Sales\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Sales\Order\OrderDetailResource;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Customer\CustomerResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesOrderDetailController extends Controller
{
    public function __construct(
        protected CustomerResolverService $customerResolverService
    ) {}

    /**
     * Show order details.
     * Supports both Customer (their order) and Admin (any order).
     */
    public function show(Request $request, $orderNumber): JsonResponse
    {
        $isAdmin = Auth::guard('admin_api')->check();
        $factoryGuard = Auth::guard('factory');
        $isFactory = $factoryGuard->check();
        $query = null;

        if ($isAdmin) {
            $query = SalesOrder::query();
        } elseif ($isFactory) {
            $query = SalesOrder::query()->where('factory_id', $factoryGuard->id());
        } else {
            $customer = $this->customerResolverService->resolve($request);
            $query = $customer->orders();
        }

        $order = $query->where(function ($q) use ($orderNumber) {
            $q->where('order_number', $orderNumber)
                ->orWhere('id', $orderNumber);
        })
            ->with([
                'customer',
                'items',
                'shippingAddress',
                'billingAddress',
                'items.options',
                'items.designs',
                'payments',
                'shipments',
                'shipments.trackingLogs',
                'statusHistory',
                'statusHistory.shippingPartner',
                'factory.business',
                'sourceInfo.channel',
            ])
            ->first();

        if (! $order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => new OrderDetailResource($order),
        ]);
    }
}
