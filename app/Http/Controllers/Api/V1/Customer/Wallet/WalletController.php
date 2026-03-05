<?php

namespace App\Http\Controllers\Api\V1\Customer\Wallet;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Models\Currency\Currency;
use App\Models\Customer\Payment\VendorWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WalletController extends Controller
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function index(Request $request)
    {
        $isAdmin = Auth::guard('admin_api')->check();

        $vendor = $isAdmin
            ? null
            : app(AccountController::class)->resolveCustomer($request);

        if (! $isAdmin && ! $vendor) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'currency' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'order_column' => ['nullable', 'string', 'in:vendor_id,balance,created_at'],
            'order_dir' => ['nullable', 'string', 'in:asc,desc'],
            'customer_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $currency = strtoupper($validated['currency'] ?? Currency::getDefaultCurrency()->code);
        $limit = $validated['limit'] ?? self::DEFAULT_LIMIT;
        $orderCol = $validated['order_column'] ?? 'created_at';
        $orderDir = $validated['order_dir'] ?? 'desc';
        $search = $validated['search'] ?? null;

        /**
         * ==========================
         * ADMIN
         * ==========================
         */
        if ($isAdmin) {

            $query = VendorWallet::query()
                ->with('vendor:id,first_name,last_name,email');
            if (! empty($validated['customer_id'])) {
                $wallet = VendorWallet::where('vendor_id', $validated['customer_id'])->first();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'balance' => format_price($wallet?->balance ?? 0, $currency),
                        'balance_raw' => $wallet?->balance ?? 0,
                        'currency' => $currency,
                    ],
                ], Response::HTTP_OK);
            }
            if (! empty($search)) {
                $query->whereHas('vendor', function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                        if (is_numeric($search)) {
                            $sub->orWhere('id', (int) $search);
                        }
                    });

                });
            }
            $wallets = $query
                ->orderBy($orderCol, $orderDir)
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $wallets->map(fn ($wallet) => [
                    'vendor_id' => $wallet->vendor_id,
                    'vendor_name' => trim(
                        optional($wallet->vendor)->first_name.' '.
                        optional($wallet->vendor)->last_name
                    ),
                    'email' => optional($wallet->vendor)->email,
                    'balance' => format_price($wallet->balance, $currency),
                    'balance_raw' => $wallet->balance,
                    'currency' => $currency,
                ]),
                'meta' => [
                    'current_page' => $wallets->currentPage(),
                    'per_page' => $wallets->perPage(),
                    'total' => $wallets->total(),
                ],
            ], Response::HTTP_OK);
        }

        /**
         * ==========================
         * VENDOR → OWN WALLET
         * ==========================
         */
        $wallet = VendorWallet::where('vendor_id', $vendor->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => format_price($wallet?->balance ?? 0, $currency),
                'balance_raw' => $wallet?->balance ?? 0,
                'currency' => $currency,
                'auto_pay_enabled' => (bool) ((int) $vendor->metaValue('auto_pay_enabled', 0)),
            ],
        ], Response::HTTP_OK);
    }

    public function toggleAutoPay(Request $request): JsonResponse
    {
        $vendor = app(AccountController::class)->resolveCustomer($request);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $isEnabled = (bool) $validated['enabled'];
        $currentAutoPay = filter_var($vendor->metaValue('auto_pay_enabled', false), FILTER_VALIDATE_BOOLEAN);
        if ($currentAutoPay === $isEnabled) {
            return response()->json([
                'success' => true,
                'message' => __('AutoPay setting is already up to date.'),
                'data' => [
                    'auto_pay_enabled' => $isEnabled,
                ],
            ], Response::HTTP_OK);
        }
        $vendor->setMetaValue('auto_pay_enabled', $isEnabled);

        return response()->json([
            'success' => true,
            'message' => $isEnabled
                ? __('AutoPay has been enabled successfully.')
                : __('AutoPay has been disabled successfully.'),
            'data' => [
                'auto_pay_enabled' => $vendor->metaValue('auto_pay_enabled', false),
            ],
        ], Response::HTTP_OK);
    }
}
