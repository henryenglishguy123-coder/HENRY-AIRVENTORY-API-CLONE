<?php

namespace App\Http\Controllers\Api\V1\Customer\Wallet;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Models\Currency\Currency;
use App\Models\Customer\Payment\VendorWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WalletTransactionController extends AccountController
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function transactions(Request $request): JsonResponse
    {
        $vendor = $this->resolveCustomer($request);
        $isAdmin = Auth::guard('admin')->check();
        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'currency' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'type' => ['nullable', 'in:credit,debit'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'status' => ['nullable', 'in:completed,pending,canceled'],
        ]);

        $currency = strtoupper(
            $validated['currency'] ?? Currency::getDefaultCurrency()->code
        );

        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $applyFilters = function ($query) use ($validated) {
            if (! empty($validated['type'])) {
                $query->where('type', $validated['type']);
            }
            if (! empty($validated['from_date'])) {
                $from = Carbon::parse($validated['from_date'])->startOfDay();
                $query->where('created_at', '>=', $from);
            }
            if (! empty($validated['to_date'])) {
                $to = Carbon::parse($validated['to_date'])->endOfDay();
                $query->where('created_at', '<=', $to);
            }
            if (! empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            return $query;
        };

        /**
         * ==========================
         * ADMIN → ALL TRANSACTIONS
         * ==========================
         */
        if ($isAdmin) {
            $transactions = \App\Models\Customer\Payment\VendorWalletTransaction::query()
                ->with('wallet.vendor:id,first_name,last_name,email')
                ->latest()
                ->paginate($limit);

            $transactions->getCollection()->transform(
                fn ($transaction) => $this->formatAdminTransaction($transaction, $currency)
            );

            return response()->json([
                'success' => true,
                'data' => $transactions,
            ], Response::HTTP_OK);
        }

        /**
         * ==========================
         * VENDOR → OWN TRANSACTIONS
         * ==========================
         */
        $wallet = VendorWallet::query()
            ->where('vendor_id', $vendor->id)
            ->first();
        if (! $wallet) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => __('Wallet not found.'),
            ], Response::HTTP_OK);
        }

        $transactions = $wallet->transactions()
            ->latest()
            ->when(true, $applyFilters)
            ->paginate($limit);

        $transactions->getCollection()->transform(
            fn ($transaction) => $this->formatVendorTransaction($transaction, $currency)
        );

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ], Response::HTTP_OK);
    }

    /**
     * ==========================
     * FORMATTERS
     * ==========================
     */
    private function formatVendorTransaction($transaction, string $currency): array
    {
        return [
            'transaction_id' => $transaction->transaction_id,
            'type' => $transaction->type,
            'amount' => format_price($transaction->amount, $currency),
            'balance_after' => format_price($transaction->balance_after, $currency),
            'payment_method' => $transaction->payment_method,
            'description' => $transaction->description,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at?->toDateTimeString(),
        ];
    }

    private function formatAdminTransaction($transaction, string $currency): array
    {
        $vendor = optional(optional($transaction->wallet)->vendor);

        return [
            'transaction_id' => $transaction->transaction_id,
            'vendor_id' => $vendor->id,
            'vendor_name' => trim(($vendor->first_name ?? '').' '.($vendor->last_name ?? '')),
            'email' => $vendor->email,
            'type' => $transaction->type,
            'amount' => format_price($transaction->amount, $currency),
            'balance_after' => format_price($transaction->balance_after, $currency),
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toDateTimeString(),
        ];
    }
}
