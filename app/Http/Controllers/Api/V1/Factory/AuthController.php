<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\VerifyEmailRequest;
use App\Models\Factory\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Verify email using verification code.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(VerifyEmailRequest $request)
    {
        try {
            DB::beginTransaction();

            $factory = Factory::where('email', $request->email)
                ->lockForUpdate()
                ->first();

            if (! $factory) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Factory not found.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if already verified
            if ($factory->email_verified_at) {
                DB::rollBack();

                // Generate login token for already verified account
                $token = Auth::guard('factory')->login($factory);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'factory' => $this->factoryPayload($factory),
                    ],
                    'message' => __('Email already verified.'),
                ], Response::HTTP_OK);
            }

            // Check if code matches
            if ($factory->email_verification_code !== $request->otp) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Invalid verification code.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if code has expired
            if (! $factory->email_verification_code_expires_at ||
                Carbon::now()->greaterThan($factory->email_verification_code_expires_at)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Verification code has expired.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update factory - mark email as verified and clear verification code
            $factory->update([
                'email_verified_at' => Carbon::now(),
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
            ]);

            DB::commit();

            // Generate login token for auto-login after verification
            $token = Auth::guard('factory')->login($factory);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'factory' => $this->factoryPayload($factory),
                ],
                'message' => __('Email verified successfully'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Email verification failed: '.$e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Email verification failed, please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Prepare factory payload for response.
     */
    protected function factoryPayload($factory): array
    {
        return [
            'name' => trim(($factory->first_name ?? '').' '.($factory->last_name ?? '')),
            'email' => $factory->email,
        ];
    }
}
