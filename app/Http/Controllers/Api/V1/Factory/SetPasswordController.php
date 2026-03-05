<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\SetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetPasswordController extends Controller
{
    /**
     * Update password for an authenticated factory account.
     */
    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $factory = Auth::guard('factory')->user();

        if (! $factory) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        DB::beginTransaction();
        try {
            // Update password (will be auto-hashed by model mutator)
            $factory->password = $request->input('password');
            $factory->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => __('Password has been updated successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Unable to update password. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
