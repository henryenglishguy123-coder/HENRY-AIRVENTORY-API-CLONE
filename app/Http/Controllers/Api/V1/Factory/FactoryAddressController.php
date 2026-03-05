<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Enums\AccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\FactoryAddressRequest;
use App\Models\Factory\Factory;
use App\Models\Factory\FactoryAddress;
use App\Models\Factory\FactoryMetas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FactoryAddressController extends Controller
{
    /**
     * Store a new factory address.
     */
    public function store(FactoryAddressRequest $request)
    {
        try {
            // Get factory_id based on user type
            $factoryId = $this->getFactoryId($request);

            if (! $factoryId) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Unable to determine factory.',
                ], 400);
            }

            // Check authorization for factory users
            if (! $this->canUpdateAddresses($factoryId)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Factory addresses cannot be updated after account verification.',
                ], 403);
            }

            DB::beginTransaction();

            $address = FactoryAddress::create([
                'factory_id' => $factoryId,
                'type' => $request->type,
                'address' => $request->address,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
            ]);

            // Update addresses_status in factory_metas
            FactoryMetas::updateOrCreate(
                ['factory_id' => $factoryId, 'key' => 'addresses_status'],
                ['value' => '1']
            );

            // Check if all registration steps are completed and update basic_info_status
            $factory = Factory::find($factoryId);
            $this->updateRegistrationStatus($factory);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => ['address' => $address],
                'message' => 'Address added successfully.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to add address: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all addresses for a factory.
     */
    public function index(Request $request)
    {
        try {
            // Get factory_id based on user type
            if (Auth::guard('admin_api')->check()) {
                $factoryId = $request->query('factory_id');

                if (! $factoryId) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Factory ID is required for admin users.',
                    ], 400);
                }

                // Validate factory exists
                if (! Factory::find($factoryId)) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Factory not found.',
                    ], 404);
                }
            } else {
                $factoryId = Auth::guard('factory')->id();
            }

            $addresses = FactoryAddress::where('factory_id', $factoryId)->get();

            return response()->json([
                'success' => true,
                'data' => ['addresses' => $addresses],
                'message' => 'Addresses retrieved successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve addresses: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a specific address.
     */
    public function update(FactoryAddressRequest $request, $id)
    {
        try {
            $address = FactoryAddress::findOrFail($id);

            // Get factory_id based on user type
            $factoryId = $this->getFactoryId($request);

            if (! $factoryId) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Unable to determine factory.',
                ], 400);
            }

            // Ensure address belongs to the factory
            if ($address->factory_id != $factoryId) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Unauthorized to update this address.',
                ], 403);
            }

            // Check authorization for factory users
            if (! $this->canUpdateAddresses($factoryId)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Factory addresses cannot be updated after account verification.',
                ], 403);
            }

            $address->update([
                'type' => $request->type,
                'address' => $request->address,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['address' => $address],
                'message' => 'Address updated successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update address: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a specific address.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $address = FactoryAddress::findOrFail($id);

            // Get factory_id based on user type
            if (Auth::guard('admin_api')->check()) {
                $factoryId = $request->query('factory_id') ?? $address->factory_id;
            } else {
                $factoryId = Auth::guard('factory')->id();
            }

            // Ensure address belongs to the factory
            if ($address->factory_id != $factoryId) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Unauthorized to delete this address.',
                ], 403);
            }

            // Check authorization for factory users
            if (! $this->canUpdateAddresses($factoryId)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Factory addresses cannot be deleted after account verification.',
                ], 403);
            }

            $address->delete();

            // Check if all registration steps are completed and update basic_info_status
            $factory = Factory::find($factoryId);
            $this->updateRegistrationStatus($factory);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Address deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete address: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get factory ID based on user type.
     */
    private function getFactoryId(Request $request): ?int
    {
        if (Auth::guard('admin_api')->check()) {
            return $request->input('factory_id') ?? $request->query('factory_id');
        }

        if (Auth::guard('factory')->check()) {
            return Auth::guard('factory')->id();
        }

        return null;
    }

    /**
     * Check if addresses can be updated.
    /**
     * Check if addresses can be updated.
     */
    private function canUpdateAddresses(int $factoryId): bool
    {
        // Admin users can always update
        if (Auth::guard('admin_api')->check()) {
            return true;
        }

        // Factory users can only update if account is not verified (account_verified != 1)
        $factory = Factory::find($factoryId);

        return $factory && $factory->account_verified !== AccountVerificationStatus::VERIFIED;
    }

    /**
     * Update registration status based on completion of all required steps.
     * Sets basic_info_status = 1 only when business info, addresses, and secondary contact are all completed.
     */
    protected function updateRegistrationStatus(Factory $factory): void
    {
        // Check if business information exists
        $hasBusinessInfo = \App\Models\Factory\FactoryBusiness::where('factory_id', $factory->id)->exists();

        // Check if at least one address exists
        $hasAddresses = FactoryAddress::where('factory_id', $factory->id)->exists();

        // Check if secondary contact exists
        $hasSecondaryContact = FactoryMetas::where('factory_id', $factory->id)
            ->where('key', 'secondary_contact')
            ->exists();

        // Set basic_info_status = 1 only if all three components are completed
        if ($hasBusinessInfo && $hasAddresses && $hasSecondaryContact) {
            $factory->setMetaValue('basic_info_status', '1');
        } else {
            // Set to 0 if not all completed
            $factory->setMetaValue('basic_info_status', '0');
        }
    }
}
