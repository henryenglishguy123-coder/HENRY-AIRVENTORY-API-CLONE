<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer\Vendor;
use App\Support\Customers\CustomerMeta;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $client = new GoogleClient([
            'client_id' => config('services.google.client_id'),
        ]);

        $payload = $client->verifyIdToken($request->token);

        if (! $payload || ! ($payload['email_verified'] ?? false)) {
            return response()->json([
                'message' => __('Invalid or unverified Google account. '),
            ], Response::HTTP_UNAUTHORIZED);
        }

        DB::beginTransaction();

        try {
            $customer = Vendor::where('email', $payload['email'])->first();
            if (! $customer) {
                $customer = Vendor::firstOrCreate(
                    ['email' => $payload['email']],
                    [
                        'first_name' => $payload['given_name'] ?? '',
                        'last_name' => $payload['family_name'] ?? '',
                        'password' => bcrypt(Str::random(32)),
                        'email_verified_at' => now(),
                        'source' => 'google_login',
                        'social_login_id' => $payload['sub'],
                    ]
                );
            }
            // Create JWT token (default TTL)
            $token = auth('customer')->login($customer);
            $customer->last_login = now();
            $customer->save();
            DB::commit();

            return response()->json([
                'token' => $token,
                'customer' => [
                    'name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
                    'email' => $customer->email,
                    'role' => 'customer',
                    'accountStatus' => AccountStatus::fromInt($customer->account_status ?? null),
                    'emailVerified' => ! is_null($customer->email_verified_at),
                    'timezone' => CustomerMeta::get($customer->id, 'timezone', 'UTC'),
                ],
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            DB::rollBack();

            if (config('app.debug')) {
                return response()->json([
                    'message' => 'Google authentication failed: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'message' => __('Authentication failed, please try again later.'),
            ], 500);
        }
    }
}
