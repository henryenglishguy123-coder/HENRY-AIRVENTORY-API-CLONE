<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AccountStatus;
use App\Enums\AccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Support\Customers\CustomerMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthMeController extends Controller
{
    /**
     * Get authenticated user information.
     * Works for both customer and factory users.
     */
    public function me(): JsonResponse
    {
        // Try customer auth first
        if (Auth::guard('customer')->check()) {
            $user = Auth::guard('customer')->user();

            return response()->json($this->formatCustomerResponse($user), Response::HTTP_OK);
        }

        // Try factory auth
        if (Auth::guard('factory')->check()) {
            $user = Auth::guard('factory')->user();

            return response()->json($this->formatFactoryResponse($user), Response::HTTP_OK);
        }

        // Try admin API auth
        if (Auth::guard('admin_api')->check()) {
            $user = Auth::guard('admin_api')->user();

            return response()->json($this->formatAdminResponse($user), Response::HTTP_OK);
        }

        return response()->json([
            'message' => __('Unauthenticated.'),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Format customer response.
     */
    protected function formatCustomerResponse($customer): array
    {
        return [
            'name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            'email' => $customer->email,
            'role' => 'customer',
            'accountStatus' => $this->getAccountStatus($customer),
            'emailVerified' => ! is_null($customer->email_verified_at),
            'timezone' => CustomerMeta::get($customer->id, 'timezone', 'UTC'),
        ];
    }

    /**
     * Format factory response.
     */
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

    /**
     * Format admin response.
     */
    protected function formatAdminResponse($admin): array
    {
        return [
            'name' => $admin->name ?? 'Admin',
            'email' => $admin->email,
            'role' => 'admin',
            'accountStatus' => AccountStatus::ENABLED->toString(),
            'emailVerified' => true,
        ];
    }

    /**
     * Get account status string from user model.
     */
    protected function getAccountStatus($user): string
    {
        $status = $user->account_status;

        if ($status instanceof AccountStatus) {
            return $status->toString();
        }

        return AccountStatus::fromInt($status ?? null);
    }

    /**
     * Get account verification status string from user model.
     */
    protected function getAccountVerificationStatus($user): string
    {
        $status = $user->account_verified;

        if ($status instanceof AccountVerificationStatus) {
            return $status->toString();
        }

        return AccountVerificationStatus::fromInt($status ?? null);
    }
}
