<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\SignupRequest;
use App\Mail\Customer\CustomerEmailVerification;
use App\Models\Customer\Vendor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SignupController extends Controller
{
    public function signup(SignupRequest $request)
    {
        DB::beginTransaction();

        try {
            $customer = Vendor::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => $request->password, // auto-hashed in model
                'source' => 'signup',
            ]);
            $plainToken = Str::random(64);
            $customer->setMetaValue('email_verification_token', hash('sha256', $plainToken));
            $customer->setMetaValue('email_verification_expires_at', Carbon::now()->addMinutes(30));

            $customerPanelUrl = rtrim(config('app.customer_panel_url'), '/');
            $verifyUrl = "{$customerPanelUrl}/auth/email-verify?token={$plainToken}";

            Mail::to($customer->email)->queue(
                new CustomerEmailVerification($customer, $verifyUrl)
            );
            event(new Registered($customer));
            $token = auth('customer')->login($customer);
            $customer->last_login = now();
            $customer->save();
            DB::commit();

            return response()->json([
                'message' => __('Your registration was successful. A verification email has been sent to your email address.'),
                'customer' => [
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'email_verified' => ! is_null($customer->email_verified_at),
                ],
                'token' => $token,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (config('app.debug')) {
                return response()->json([
                    'message' => 'Signup failed: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'message' => __('Signup failed, please try again later.'),
            ], 500);
        }
    }
}
