<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\BusinessInformationRequest;
use App\Models\Factory\Factory;
use App\Models\Factory\FactoryBusiness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BusinessInformationController extends Controller
{
    /**
     * Store or update factory business information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(BusinessInformationRequest $request)
    {
        try {
            DB::beginTransaction();

            // Determine factory_id based on who is making the request
            if (Auth::guard('admin_api')->check()) {
                // Admin is making the request, use factory_id from request
                $factoryId = $request->factory_id;
                $factory = Factory::find($factoryId);

                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_NOT_FOUND);
                }
            } else {
                // Factory user is making the request, use their ID from token
                $factory = Auth::guard('factory')->user();
                $factoryId = $factory->id;

                // Check if factory user is allowed to update
                // Factory users can only update if account is not verified yet
                if (! $this->canUpdateBusinessInfo($factory)) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Business information cannot be updated after account verification.'),
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            // Get existing business record if it exists
            $existingBusiness = FactoryBusiness::where('factory_id', $factoryId)->first();

            // Prepare business data
            $businessData = [
                'company_name' => $request->company_name,
                'registration_number' => $request->registration_number,
                'tax_vat_number' => $request->tax_vat_number,
                'registered_address' => $request->registered_address,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'factory_id' => $factoryId,
            ];

            // Handle file uploads and cleanup old files
            $businessData['registration_certificate'] = $this->handleFileUpload(
                $request,
                'registration_certificate',
                $existingBusiness?->registration_certificate
            );

            $businessData['tax_certificate'] = $this->handleFileUpload(
                $request,
                'tax_certificate',
                $existingBusiness?->tax_certificate
            );

            $businessData['import_export_certificate'] = $this->handleFileUpload(
                $request,
                'import_export_certificate',
                $existingBusiness?->import_export_certificate
            );

            // Create or update business information
            $business = FactoryBusiness::updateOrCreate(
                ['factory_id' => $factoryId],
                $businessData
            );

            // Sync shipping partners if provided (only if admin is updating)
            if (auth()->guard('admin_api')->check()) {
                $partnerId = $request->input('shipping_partner_id');
                $factory->shippingPartners()->sync($partnerId ? [$partnerId] : []);
            }

            // Check if all registration steps are completed and update basic_info_status
            $this->updateRegistrationStatus($factory);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'business' => [
                        'id' => $business->id,
                        'company_name' => $business->company_name,
                        'registration_number' => $business->registration_number,
                        'tax_vat_number' => $business->tax_vat_number,
                        'registered_address' => $business->registered_address,
                        'country_id' => $business->country_id,
                        'state_id' => $business->state_id,
                        'city' => $business->city,
                        'postal_code' => $business->postal_code,
                        'registration_certificate' => $business->registration_certificate ? getImageUrl($business->registration_certificate) : null,
                        'tax_certificate' => $business->tax_certificate ? getImageUrl($business->tax_certificate) : null,
                        'import_export_certificate' => $business->import_export_certificate ? getImageUrl($business->import_export_certificate) : null,
                    ],
                    'shipping_partner_id' => $factory->shippingPartners()->first()?->id,
                ],
                'message' => __('Business information saved successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Failed to save business information: '.$e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Failed to save business information. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle file upload and cleanup old file.
     */
    protected function handleFileUpload(BusinessInformationRequest $request, string $fieldName, ?string $oldFilePath): ?string
    {
        if (! $request->hasFile($fieldName)) {
            return $oldFilePath;
        }

        // Store new file first
        $newFilePath = $request->file($fieldName)->store('factory/certificates');

        // Only delete old file after new file is successfully stored
        if ($newFilePath && $oldFilePath && Storage::exists($oldFilePath)) {
            Storage::delete($oldFilePath);
        }

        return $newFilePath;
    }

    /**
     * Check if the user can update business information.
     * Factory users can only update if account is not verified yet.
     * Admin users can update anytime.
     *
     * @param  mixed  $factory
     */
    protected function canUpdateBusinessInfo($factory): bool
    {
        // Check if current request is authenticated via admin guard
        // Admin users can update business info anytime
        if (Auth::guard('admin_api')->check()) {
            return true;
        }

        // For factory users, check if account is not verified yet
        // account_verified: 0=rejected, 1=verified, 2=pending, 3=hold, 4=processing
        // Allow update if account_verified is NOT 1 (verified)
        return $factory && $factory->account_verified !== 1;
    }

    /**
     * Get factory business information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(\Illuminate\Http\Request $request)
    {
        try {
            // Determine factory_id based on who is making the request
            if (Auth::guard('admin_api')->check()) {
                // Admin is making the request, factory_id must be provided as query parameter
                $factoryId = $request->query('factory_id');

                if (! $factoryId) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory ID is required for admin users.'),
                    ], Response::HTTP_BAD_REQUEST);
                }

                // Validate factory exists
                $factory = Factory::find($factoryId);
                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_NOT_FOUND);
                }
            } else {
                // Factory user is making the request, use their ID from token
                $factory = Auth::guard('factory')->user();
                $factoryId = $factory->id;
            }

            $business = FactoryBusiness::where('factory_id', $factoryId)->first();

            if (! $business) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => __('No business information found.'),
                ], Response::HTTP_OK);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'business' => [
                        'id' => $business->id,
                        'company_name' => $business->company_name,
                        'registration_number' => $business->registration_number,
                        'tax_vat_number' => $business->tax_vat_number,
                        'registered_address' => $business->registered_address,
                        'country_id' => $business->country_id,
                        'state_id' => $business->state_id,
                        'city' => $business->city,
                        'postal_code' => $business->postal_code,
                        'registration_certificate' => $business->registration_certificate ? getImageUrl($business->registration_certificate) : null,
                        'tax_certificate' => $business->tax_certificate ? getImageUrl($business->tax_certificate) : null,
                        'import_export_certificate' => $business->import_export_certificate ? getImageUrl($business->import_export_certificate) : null,
                    ],
                    'shipping_partner_id' => $factory ? $factory->shippingPartners()->first()?->id : null,
                    'myze_api_url' => $factory ? $factory->metaValue('myze_api_url') : null,
                    'myze_api_token' => $factory ? $factory->metaValue('myze_api_token') : null,
                ],
                'message' => __('Business information retrieved successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Failed to retrieve business information: '.$e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Failed to retrieve business information. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update registration status based on completion of all required steps.
     * Sets basic_info_status = 1 only when business info, addresses, and secondary contact are all completed.
     */
    protected function updateRegistrationStatus(Factory $factory): void
    {
        // Check if business information exists
        $hasBusinessInfo = FactoryBusiness::where('factory_id', $factory->id)->exists();

        // Check if at least one address exists
        $hasAddresses = \App\Models\Factory\FactoryAddress::where('factory_id', $factory->id)->exists();

        // Check if secondary contact exists
        $hasSecondaryContact = \App\Models\Factory\FactoryMetas::where('factory_id', $factory->id)
            ->where('key', 'secondary_contact')
            ->exists();

        // Set basic_info_status = 1 only if all three components are completed
        if ($hasBusinessInfo && $hasAddresses && $hasSecondaryContact) {
            $factory->setMetaValue('basic_info_status', '1');
        } else {
            // Optionally set to 0 or remove if not all completed
            // For now, we'll just not set it to 1 if incomplete
            $factory->setMetaValue('basic_info_status', '0');
        }
    }

    /**
     * Update factory shipping partner.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateShippingPartner(\Illuminate\Http\Request $request)
    {
        try {
            DB::beginTransaction();

            $rules = [
                'shipping_partner_id' => 'nullable|integer|exists:shipping_partners,id',
                'myze_api_url' => 'nullable|url',
                'myze_api_token' => 'nullable|string',
            ];

            if (Auth::guard('admin_api')->check()) {
                $rules['factory_id'] = 'required|integer|exists:factory_users,id';
            }

            $request->validate($rules);

            if (Auth::guard('admin_api')->check()) {
                $factoryId = $request->factory_id;
                $factory = Factory::find($factoryId);

                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_NOT_FOUND);
                }

                $partnerId = $request->input('shipping_partner_id');
                $factory->shippingPartners()->sync($partnerId ? [$partnerId] : []);

                if ($request->has('myze_api_url')) {
                    $factory->setMetaValue('myze_api_url', $request->input('myze_api_url'));
                }
                if ($request->has('myze_api_token')) {
                    $factory->setMetaValue('myze_api_token', $request->input('myze_api_token'));
                }
            } else {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Only admin can update shipping partners.'),
                ], Response::HTTP_FORBIDDEN);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'shipping_partner_id' => $factory->shippingPartners()->first()?->id,
                    'myze_api_url' => $factory->metaValue('myze_api_url'),
                    'myze_api_token' => $factory->metaValue('myze_api_token'),
                ],
                'message' => __('Shipping partner updated successfully.'),
            ], Response::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Failed to update shipping partner: '.$e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Failed to update shipping partner. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
