<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\UpdateAccountRequest;
use App\Models\Factory\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * Update factory account information
     *
     * Factory users can update: first_name, last_name, phone_number
     * Admin users can update all fields including email
     */
    public function update(UpdateAccountRequest $request): JsonResponse
    {
        try {
            // Determine factory_id
            $factoryId = $this->getFactoryId($request);

            // Find factory
            $factory = Factory::find($factoryId);
            if (! $factory) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Factory not found.',
                ], 404);
            }

            // Prepare update data
            $updateData = [];

            // Fields that both factory and admin can update
            if ($request->has('first_name')) {
                $updateData['first_name'] = $request->first_name;
            }
            if ($request->has('last_name')) {
                $updateData['last_name'] = $request->last_name;
            }
            if ($request->has('phone_number')) {
                $updateData['phone_number'] = $request->phone_number;
            }

            // Email can only be updated by admin
            if ($this->isAdmin() && $request->has('email')) {
                // Check if email is already taken by another factory
                $existingFactory = Factory::where('email', $request->email)
                    ->where('id', '!=', $factoryId)
                    ->first();

                if ($existingFactory) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Email is already taken.',
                    ], 422);
                }

                $updateData['email'] = $request->email;
            }

            // Update factory
            $factory->update($updateData);
            $factory->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'factory' => [
                        'id' => $factory->id,
                        'first_name' => $factory->first_name,
                        'last_name' => $factory->last_name,
                        'email' => $factory->email,
                        'phone_number' => $factory->phone_number,
                    ],
                ],
                'message' => 'Account information updated successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'An error occurred while updating account information.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get factory ID based on user type
     */
    private function getFactoryId($request): ?int
    {
        if ($this->isAdmin()) {
            // Admin must provide factory_id
            return $request->factory_id;
        }

        // Factory user - get from authenticated user
        return Auth::guard('factory')->id();
    }

    /**
     * Check if authenticated user is admin
     */
    private function isAdmin(): bool
    {
        return Auth::guard('admin_api')->check();
    }
}
