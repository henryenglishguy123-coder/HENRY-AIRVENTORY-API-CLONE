<?php

namespace App\Http\Controllers\Api\V1\Customer\Payment;

use App\Http\Controllers\Controller;
use App\Services\Customer\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SavedPaymentMethodController extends Controller
{
    /**
     * Create setup intent for selected payment method
     */
    public function createSetupIntent(Request $request)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        try {
            $gateway = PaymentGatewayManager::resolve(
                $validated['payment_method']
            );

            $intent = $gateway->createSetupIntent($customer->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $intent->client_secret,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Save payment method (card / agreement / etc.)
     */
    public function store(Request $request)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
            'payment_method_id' => ['required', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        try {
            $gateway = PaymentGatewayManager::resolve(
                $validated['payment_method']
            );

            $gateway->saveMethod([
                'vendor_id' => $customer->id,
                'payment_method_id' => $validated['payment_method_id'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Payment method saved successfully'),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * List saved payment methods
     */
    public function index(Request $request)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        try {
            $gateway = PaymentGatewayManager::resolve(
                $validated['payment_method']
            );

            return response()->json([
                'success' => true,
                'data' => $gateway->listMethods($customer->id),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        try {
            $gateway = PaymentGatewayManager::resolve(
                $validated['payment_method']
            );

            $gateway->deleteMethod($customer->id, $id);

            return response()->json([
                'success' => true,
                'message' => __('Payment method deleted successfully'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
