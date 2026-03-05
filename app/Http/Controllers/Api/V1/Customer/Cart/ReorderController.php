<?php

namespace App\Http\Controllers\Api\V1\Customer\Cart;

use App\Http\Controllers\Controller;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Customer\Cart\Actions\ReorderAction;
use App\Services\Customer\CustomerResolverService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ReorderController extends Controller
{
    /**
     * Reorder items from a previous order into the customer's active cart.
     *
     * POST /api/v1/customers/orders/{order}/reorder
     */
    public function reorder(
        Request $request,
        $orderNumber,
        ReorderAction $action,
        CustomerResolverService $customerResolver
    ): JsonResponse {
        try {
            $order = SalesOrder::where('order_number', $orderNumber)->orWhere('id', $orderNumber)->firstOrFail();
            $customer = $customerResolver->resolve($request);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => __('Customer could not be resolved.'),
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Authentication failed: ' . $e->getMessage()),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($customer->cannot('reorder', $order)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to reorder this order.'),
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $result = $action->execute($customer, $order);

            if (!is_array($result) || !isset($result['cart'], $result['added'], $result['skipped'])) {
                Log::error('ReorderAction returned invalid structure', [
                    'order_id' => $order->id,
                    'result_type' => gettype($result),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => __('An unexpected error occurred during reordering.'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $message = count($result['skipped']) > 0
                ? __('Order partially reordered. Some items were skipped.')
                : __('Order reordered successfully.');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cart' => $result['cart'],
                    'added_items' => $result['added'],
                    'skipped_items' => $result['skipped'],
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Reorder failed', [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to reorder. Please try again.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
