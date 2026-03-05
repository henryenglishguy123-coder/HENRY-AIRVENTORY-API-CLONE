<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\ResendOtpRequest;
use App\Mail\Factory\VerificationCodeMail;
use App\Models\Factory\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class ResendOtpController extends Controller
{
    /**
     * Resend OTP for factory email verification.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendOtp(ResendOtpRequest $request)
    {
        try {
            $factory = Factory::where('email', $request->email)->first();

            // Check if email is already verified
            if ($factory->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Email is already verified.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Generate new 6-digit verification code
            $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Update factory with new verification code
            $factory->update([
                'email_verification_code' => $verificationCode,
                'email_verification_code_expires_at' => Carbon::now()->addMinutes(15),
            ]);

            // Send verification code via email
            Mail::to($factory->email)->queue(
                new VerificationCodeMail($factory, $verificationCode)
            );

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => __('Verification code has been resent to your email.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Resend OTP failed: '.$e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Failed to resend verification code, please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
