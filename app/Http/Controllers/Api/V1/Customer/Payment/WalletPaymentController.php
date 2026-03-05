<?php

namespace App\Http\Controllers\Api\V1\Customer\Payment;

use App\Http\Controllers\Controller;
use App\Services\Customer\Payments\PaymentGatewayManager;
use App\Services\Customer\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Symfony\Component\HttpFoundation\Response;

class WalletPaymentController extends Controller
{
    public function topup(Request $request)
    {
        $vendor = auth('customer')->user();

        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => __('Authentication required. Please log in again.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
            'payment_method_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $gateway = PaymentGatewayManager::resolve($validated['payment_method']);
            $charge = $gateway->chargeSavedMethod(
                vendorId: $vendor->id,
                payment_method_id: $validated['payment_method_id'],
                amount: $validated['amount']
            );
            if (isset($charge['payment_intent_id'])) {
                try {
                    WalletService::initiateCredit(
                        vendorId: $vendor->id,
                        amount: $validated['amount'],
                        paymentMethod: $validated['payment_method'],
                        description: __('Wallet top-up via '.ucfirst($validated['payment_method'])),
                        reference: $charge['payment_intent_id']
                    );
                } catch (\Illuminate\Database\QueryException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') === false &&
                        strpos($e->getMessage(), 'UNIQUE constraint failed') === false) {
                        throw $e;
                    }
                    Log::info('Transaction already exists for Payment Intent: '.$charge['payment_intent_id']);
                }
            }
            if (! empty($charge['requires_action'])) {
                return response()->json([
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $charge['client_secret'],
                    'payment_intent_id' => $charge['payment_intent_id'],
                ], Response::HTTP_OK);
            }
            if (empty($charge['success'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('Payment failed. Please try again.'),
                ], Response::HTTP_PAYMENT_REQUIRED);
            }
            $wallet = WalletService::confirmCredit($charge['payment_intent_id']);

            return response()->json([
                'success' => true,
                'message' => __('Wallet topped up successfully'),
                'data' => [
                    'balance' => format_price($wallet->balance),
                ],
            ], Response::HTTP_OK);
        } catch (CardException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Your card was declined. Please try another card.'),
            ], Response::HTTP_PAYMENT_REQUIRED);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Payment service is temporarily unavailable. Please try again later.'),
            ], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $e) {
            Log::error('Wallet top-up failed', [
                'vendor_id' => $vendor->id ?? null,
                'amount' => $validated['amount'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Unable to complete wallet top-up. Please try again or contact support.'),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function confirm(Request $request)
    {
        $vendor = auth('customer')->user();
        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => __('Authentication required.'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string'],
            'payment_method' => ['required', 'string'],
        ]);
        try {
            $gateway = PaymentGatewayManager::resolve($validated['payment_method']);
            $result = $gateway->confirmPaymentIntent(
                vendorId: $vendor->id,
                paymentIntentId: $validated['payment_intent_id']
            );
            if (empty($result['success'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? __('Payment not completed yet.'),
                    'status' => $result['status'] ?? null,
                ], Response::HTTP_BAD_REQUEST);
            }

            $wallet = WalletService::confirmCredit($result['payment_intent_id']);
            if (! $wallet) {
                WalletService::initiateCredit(
                    vendorId: $vendor->id,
                    amount: $result['amount'],
                    paymentMethod: $validated['payment_method'],
                    description: __('Wallet top-up via '.$validated['payment_method']),
                    reference: $result['payment_intent_id']
                );
                $wallet = WalletService::confirmCredit($result['payment_intent_id']);
            }
            if (! $wallet) {
                return response()->json([
                    'success' => false,
                    'message' => __('Unable to confirm wallet credit.'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => true,
                'message' => __('Wallet topped up successfully.'),
                'data' => [
                    'balance' => format_price($wallet->balance),
                    'status' => 'completed',
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Wallet topup confirmation failed', [
                'vendor_id' => $vendor->id,
                'payment_intent_id' => $validated['payment_intent_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Unable to confirm wallet top-up. Please contact support.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
