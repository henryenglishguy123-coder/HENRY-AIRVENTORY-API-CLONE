<?php

namespace App\Http\Controllers\Api\V1\Sales\Order;

use App\Http\Controllers\Controller;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Customer\CustomerResolverService;
use App\Services\Sales\Order\OrderPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SalesOrderPaymentController extends Controller
{
    public function __construct(
        protected OrderPaymentService $orderPaymentService,
        protected CustomerResolverService $customerResolverService
    ) {}

    public function pay(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => ['integer'], // SALES ORDER IDs
            'order_id' => ['nullable', 'integer'], // SALES ORDER ID
            'payment_method' => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'string', 'required_with:payment_method'],
            'use_wallet' => ['sometimes', 'boolean'],
        ]);

        $orderIds = $validated['order_ids'] ?? [];
        if (! empty($validated['order_id'])) {
            $orderIds[] = $validated['order_id'];
        }
        $orderIds = array_values(array_unique($orderIds));

        if (empty($orderIds)) {
            return response()->json([
                'success' => false,
                'message' => __('Please provide order_id or order_ids.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $useWallet = (bool) ($validated['use_wallet'] ?? false);

        if (! $useWallet && empty($validated['payment_method'])) {
            return response()->json([
                'success' => false,
                'message' => __('Please select a payment method or enable wallet payment.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $customer = $this->customerResolverService->resolve($request);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        $orders = SalesOrder::query()
            ->with('payments')
            ->whereIn('id', $orderIds)
            ->where('customer_id', $customer->id)
            ->get();

        if ($orders->count() !== count($orderIds)) {
            return response()->json([
                'success' => false,
                'message' => __('One or more orders not found or unauthorized.'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->orderPaymentService->processPayment($orders, $validated);

            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Order payment failed', [
                'order_ids' => $orderIds,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while processing the payment.'),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
