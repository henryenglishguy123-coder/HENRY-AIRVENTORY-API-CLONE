<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Enums\AccountStatus;
use App\Enums\AccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Mail\Factory\FactoryGoogleLoginPasswordMail;
use App\Models\Factory\Factory;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

        try {
            $payload = $client->verifyIdToken($request->token);

            if (! $payload || ! ($payload['email_verified'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => __('Invalid or unverified Google account.'),
                ], Response::HTTP_UNAUTHORIZED);
            }

            DB::beginTransaction();

            $factory = Factory::where('email', $payload['email'])->first();

            if (! $factory) {
                $randomPassword = Str::random(32);

                $factory = Factory::create([
                    'email' => $payload['email'],
                    'first_name' => substr(trim($payload['given_name'] ?? ''), 0, 255),
                    'last_name' => substr(trim($payload['family_name'] ?? ''), 0, 255),
                    'password' => $randomPassword,
                    'source' => 'google_login',
                    'google_id' => $payload['sub'],
                    'email_verified_at' => now(),
                ]);

                $plainToken = Str::random(64);
                $factory->setMetaValue('password_reset_token', hash('sha256', $plainToken));
                $factory->setMetaValue('password_reset_expires_at', Carbon::now()->addDay());

                $factoryPanelUrl = rtrim(config('app.factory_panel_url', config('app.url')), '/');
                $encodedEmail = rawurlencode($factory->email);
                $resetUrl = "{$factoryPanelUrl}/auth/reset-password?token={$plainToken}&email={$encodedEmail}";

                DB::afterCommit(function () use ($factory, $resetUrl) {
                    Mail::to($factory->email)->queue(
                        new FactoryGoogleLoginPasswordMail($factory, $resetUrl)
                    );
                });
            } else {
                if ($factory->google_id) {
                    if ($factory->google_id !== $payload['sub']) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => __('This user account is already linked to a different Google account.'),
                        ], Response::HTTP_CONFLICT);
                    }
                } else {
                    if ($factory->source === 'google_login') {
                        $factory->google_id = $payload['sub'];
                        $factory->save();
                    } else {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => __('An account with this email already exists. Please sign in with your email and password, then link Google from your account settings.'),
                            'code' => 'google_account_link_required',
                        ], Response::HTTP_CONFLICT);
                    }
                }
            }

            // Check if email is verified
            if (! $factory->email_verified_at) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => __('Email address not verified. Please verify your email before logging in.'),
                ], Response::HTTP_FORBIDDEN);
            }

            // Check account status (if active)
            if ($factory->account_status === 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => __('Your account is inactive. Please contact support.'),
                ], Response::HTTP_FORBIDDEN);
            }

            $token = auth('factory')->login($factory);

            // Update last login timestamp
            $factory->forceFill(['last_login' => now()])->saveQuietly();

            DB::commit();

            return response()->json([
                'success' => true,
                'token' => $token,
                'factory' => [
                    'name' => trim(($factory->first_name ?? '').' '.($factory->last_name ?? '')),
                    'email' => $factory->email,
                ],
                'message' => __('Successfully logged in.'),
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            DB::rollBack();

            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google authentication failed: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => __('Authentication failed, please try again later.'),
            ], 500);
        }
    }

    protected function formatFactoryResponse($factory): array
    {
        return [
            'firstName' => trim($factory->first_name ?? ''),
            'lastName' => trim($factory->last_name ?? ''),
            'email' => $factory->email,
            'company_name' => $factory?->business?->company_name ?? null,
            'phone_number' => $factory->phone_number,
            'role' => 'factory',
            'accountStatus' => $this->getAccountStatus($factory),
            'emailVerified' => ! is_null($factory->email_verified_at),
            'accountVerified' => $this->getAccountVerificationStatus($factory),
        ];
    }

    protected function getAccountStatus($user): string
    {
        $status = $user->account_status;

        if ($status instanceof AccountStatus) {
            return $status->toString();
        }

        return AccountStatus::fromInt($status ?? null);
    }

    protected function getAccountVerificationStatus($user): string
    {
        $status = $user->account_verified;

        if ($status instanceof AccountVerificationStatus) {
            return $status->toString();
        }

        return AccountVerificationStatus::fromInt($status ?? null);
    }
}
