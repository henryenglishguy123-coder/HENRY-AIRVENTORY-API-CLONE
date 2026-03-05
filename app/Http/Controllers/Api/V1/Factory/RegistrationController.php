<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\RegistrationRequest;
use App\Mail\Factory\VerificationCodeMail;
use App\Models\Factory\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends Controller
{
    /**
     * Handle factory registration.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegistrationRequest $request)
    {
        DB::beginTransaction();

        try {
            // Generate 6-digit verification code
            $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Create factory
            $factory = Factory::create([
                'first_name' => $request->firstname,
                'last_name' => $request->lastname,
                'email' => $request->email,
                'phone_number' => $request->phone,
                'password' => $request->password, // auto-hashed in model
                'source' => 'signup',
                'email_verification_code' => $verificationCode,
                'email_verification_code_expires_at' => Carbon::now()->addMinutes(15),
                'email_verified_at' => null,
            ]);

            // Associate factory with industry
            $factory->industries()->attach($request->industry_id);

            // Send verification code via email
            Mail::to($factory->email)->queue(
                new VerificationCodeMail($factory, $verificationCode)
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => __('Registration successful. Please check your email for verification code.'),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Registration failed: '.$e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Registration failed, please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
