<?php

namespace App\Http\Controllers\Api\V1\Customer\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Account\UpdateAccountRequest;
use App\Models\Customer\Vendor;
use App\Support\Customers\CustomerMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    protected int $cacheTtlSeconds;

    public function __construct()
    {
        $this->cacheTtlSeconds = (int) config('cache.account_ttl', 60);
    }

    public function show(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $cacheKey = $this->cacheKey($customer->id);

        $payload = Cache::remember(
            $cacheKey,
            now()->addSeconds($this->cacheTtlSeconds),
            fn () => $this->minimalPayload($customer)
        );

        return response()->json([
            'success' => true,
            'message' => __('Account details fetched successfully.'),
            'customer' => $payload,
        ], Response::HTTP_OK);
    }

    public function update(UpdateAccountRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        DB::beginTransaction();
        try {
            if ($request->filled('first_name')) {
                $customer->first_name = $request->first_name;
            }
            if ($request->filled('last_name')) {
                $customer->last_name = $request->last_name;
            }
            if ($request->filled('phone')) {
                $customer->mobile = $request->phone;
            }
            if ($request->has('notify_email')) {
                CustomerMeta::update($customer->id, 'notify_email', (bool) $request->notify_email);
            }
            if ($request->has('fulfillment_type')) {
                CustomerMeta::update($customer->id, 'fulfillment_type', $request->fulfillment_type);
            }
            if ($request->has('allow_split_orders')) {
                CustomerMeta::update($customer->id, 'allow_split_orders', (bool) $request->allow_split_orders);
            }
            if ($request->filled('password')) {
                $customer->password = $request->password;
            }
            if ($request->filled('timezone')) {
                CustomerMeta::update($customer->id, 'timezone', $request->timezone);
            }
            $customer->save();
            DB::commit();
            $cacheKey = $this->cacheKey($customer->id);
            $payload = $this->minimalPayload($customer);
            Cache::put($cacheKey, $payload, now()->addSeconds($this->cacheTtlSeconds));

            return response()->json([
                'success' => true,
                'message' => __('Your account and routing preferences have been updated.'),
                'customer' => $payload,
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'message' => __('Unable to update account. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    protected function minimalPayload($customer): array
    {
        return [
            'first_name' => $customer->first_name ?? null,
            'last_name' => $customer->last_name ?? null,
            'email' => $customer->email ?? null,
            'phone' => $customer->mobile ?? null,
            'timezone' => CustomerMeta::get($customer->id, 'timezone', 'UTC'),
            'notify_email' => (bool) (CustomerMeta::get($customer->id, 'notify_email') ?? false),
            'fulfillment_type' => CustomerMeta::get($customer->id, 'fulfillment_type') ?? null,
            'allow_split_orders' => (bool) (CustomerMeta::get($customer->id, 'allow_split_orders') ?? false),
            'updated_at' => $customer->updated_at,
        ];
    }

    protected function cacheKey($customerId): string
    {
        return "customer:{$customerId}:account";
    }

    public function resolveCustomer(Request $request)
    {
        if (Auth::guard('customer')->check()) {
            return Auth::guard('customer')->user();
        }
        if (Auth::guard('admin_api')->check()) {
            $customerId = $request->input('customer_id');
            if (! $customerId) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'customer_id is required for admin access');
            }

            return Vendor::findOrFail($customerId);
        }
        abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated');
    }
}
