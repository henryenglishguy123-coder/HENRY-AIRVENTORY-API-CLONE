<?php

namespace App\Http\Controllers\Api\V1\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Payment\VendorWallet;
use App\Models\Customer\Payment\VendorWalletTransaction;
use App\Models\Customer\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->get('length', 10);
        $start = (int) $request->get('start', 0);
        $page = ($start / $limit) + 1;
        $sortableColumns = [
            'id',
            'first_name',
            'last_name',
            'email',
            'account_status',
            'email_verified_at',
            'created_at',
            'last_login',
        ];

        $query = Vendor::query()->select([
            'id',
            'first_name',
            'last_name',
            'email',
            'account_status',
            'email_verified_at',
            'created_at',
            'last_login',
        ]);

        /* ================= FILTERS ================= */

        if ($request->filled('account_status')) {
            $query->where('account_status', $request->account_status);
        }

        if ($request->filled('verified')) {
            $request->boolean('verified')
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }

        if ($request->filled('searchInput')) {
            $search = trim((string) $request->searchInput);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        /* ================= SORTING ================= */

        if ($request->has('order.0.column')) {
            $columnIndex = $request->order[0]['column'];
            $direction = $request->order[0]['dir'] ?? 'desc';
            $columnName = $request->columns[$columnIndex]['data'] ?? null;

            if (in_array($columnName, $sortableColumns)) {
                $query->orderBy($columnName, $direction);
            }
        } else {
            // default sort
            $query->orderByDesc('created_at');
        }

        /* ================= PAGINATION ================= */

        $customers = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'draw' => intval($request->draw),
            'recordsTotal' => $customers->total(),
            'recordsFiltered' => $customers->total(),
            'data' => $customers->items(),
        ], Response::HTTP_OK);
    }

    public function fund(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:vendors,id',
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
        ]);
        DB::beginTransaction();
        try {
            /** @var VendorWallet $wallet */
            $wallet = VendorWallet::where('vendor_id', $request->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($validated['type'] === 'debit' && bccomp($wallet->balance, $validated['amount'], 2) < 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('Insufficient wallet balance.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $newBalance = $validated['type'] === 'credit'
                ? bcadd($wallet->balance, $validated['amount'], 2)
                : bcsub($wallet->balance, $validated['amount'], 2);
            $wallet->update([
                'balance' => $newBalance,
            ]);

            VendorWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => 'VW-'.strtoupper(Str::random(16)),
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'payment_method' => 'admin',
                'description' => $validated['description'],
                'status' => VendorWalletTransaction::STATUS_COMPLETED,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Wallet updated successfully.'),
                'balance' => format_price($newBalance),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('Unable to update wallet. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
