<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\SecondaryContactRequest;
use App\Models\Factory\Factory;
use App\Models\Factory\FactoryMetas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecondaryContactController extends Controller
{
    /**
     * Store or update secondary contact information
     */
    public function store(SecondaryContactRequest $request)
    {
        try {
            DB::beginTransaction();

            $factoryId = $this->getFactoryId($request);

            // Validate factory exists for admin users
            if (Auth::guard('admin_api')->check() && ! Factory::find($factoryId)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Factory not found.',
                ], 404);
            }

            $contactData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
            ];

            // Add email only if provided
            if ($request->filled('email')) {
                $contactData['email'] = $request->email;
            }

            // Store secondary contact as JSON in factory_metas
            FactoryMetas::updateOrCreate(
                [
                    'factory_id' => $factoryId,
                    'key' => 'secondary_contact',
                ],
                [
                    'value' => json_encode($contactData),
                ]
            );

            // Check if all registration steps are completed and update basic_info_status
            $factory = Factory::find($factoryId);
            $this->updateRegistrationStatus($factory);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'secondary_contact' => $contactData,
                ],
                'message' => 'Secondary contact information saved successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to save secondary contact information.',
            ], 500);
        }
    }

    /**
     * Retrieve secondary contact information
     */
    public function show(Request $request)
    {
        try {
            $factoryId = $this->getFactoryId($request);

            // Validate factory exists for admin users
            if (Auth::guard('admin_api')->check()) {
                if (! $request->filled('factory_id')) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'factory_id parameter is required for admin users.',
                    ], 400);
                }

                if (! Factory::find($factoryId)) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Factory not found.',
                    ], 404);
                }
            }

            $secondaryContact = FactoryMetas::where('factory_id', $factoryId)
                ->where('key', 'secondary_contact')
                ->first();

            if (! $secondaryContact) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'secondary_contact' => null,
                    ],
                    'message' => 'No secondary contact information found.',
                ], 200);
            }

            $contactData = json_decode($secondaryContact->value, true);

            return response()->json([
                'success' => true,
                'data' => [
                    'secondary_contact' => $contactData,
                ],
                'message' => 'Secondary contact information retrieved successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve secondary contact information.',
            ], 500);
        }
    }

    /**
     * Get factory_id from request or authenticated user
     */
    private function getFactoryId(Request $request): int
    {
        // Admin users must provide factory_id
        if (Auth::guard('admin_api')->check()) {
            return (int) $request->factory_id;
        }

        // Factory users use their authenticated ID
        return Auth::guard('factory')->id();
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
        $hasAddresses = \App\Models\Factory\FactoryAddress::where('factory_id', $factory->id)->exists();

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
