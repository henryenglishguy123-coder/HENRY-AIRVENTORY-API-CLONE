<?php

namespace App\Http\Controllers\Api\V1\Customer\Template;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Template\VendorDesignTemplateStoreImageUploadRequest;
use App\Http\Requests\Api\V1\Customer\Template\VendorDesignTemplateStoreUpdateRequest;
use App\Http\Resources\Api\V1\Customer\Template\VendorDesignTemplateResource;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreImage;
use App\Services\Customer\Template\VendorDesignTemplateStoreService;
use App\Traits\ApiResponse;
use Illuminate\Http\Response;

class VendorDesignTemplateStoreController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VendorDesignTemplateStoreService $vendorDesignTemplateStoreService
    ) {}

    public function update(VendorDesignTemplateStoreUpdateRequest $request, VendorDesignTemplate $template)
    {
        // Use Policy for authorization
        // $this->authorize('update', $template);

        try {
            // Block updates for link-only overrides
            $override = VendorDesignTemplateStore::where('vendor_design_template_id', $template->id)
                ->where('vendor_connected_store_id', $request->store_id)
                ->first();
            if ($override && ($override->is_link_only ?? false)) {
                return $this->errorResponse(
                    'Updates are disabled for link-only products.',
                    Response::HTTP_FORBIDDEN
                );
            }

            // Use template's vendor_id since authorization passed (ensures owner or admin acting on behalf)
            $data = $request->validated();
            $data['status'] = 'active';

            $this->vendorDesignTemplateStoreService->updateStoreSettings($template, $data, $template->vendor_id);

            // Reload template with relationships
            $template->loadDetails();

            // Eager load the specific store override we just updated
            $template->loadStoreSpecificDetails($request->store_id);

            return $this->successResponse(
                new VendorDesignTemplateResource($template),
                'Store template settings updated successfully.'
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update store template settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'template_id' => $template->id,
                'vendor_id' => $template->vendor_id,
            ]);

            return $this->errorResponse(
                'An error occurred while updating store template settings.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function uploadImage(VendorDesignTemplateStoreImageUploadRequest $request)
    {
        try {
            $path = $this->vendorDesignTemplateStoreService->uploadImage(
                $request->file('image'),
                $request->store_id
            );

            return $this->successResponse(
                [
                    'url' => getImageUrl($path),
                    'path' => $path,
                ],
                'Image uploaded successfully.'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to upload store template image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'store_id' => $request->store_id,
            ]);

            return $this->errorResponse(
                'An error occurred while uploading the image.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function saveDraft(VendorDesignTemplateStoreUpdateRequest $request, VendorDesignTemplate $template)
    {
        // Use Policy for authorization
        // $this->authorize('update', $template);

        try {
            // Block draft updates for link-only overrides
            $override = VendorDesignTemplateStore::where('vendor_design_template_id', $template->id)
                ->where('vendor_connected_store_id', $request->store_id)
                ->first();
            if ($override && ($override->is_link_only ?? false)) {
                return $this->errorResponse(
                    'Draft updates are disabled for link-only products.',
                    Response::HTTP_FORBIDDEN
                );
            }

            // Use template's vendor_id since authorization passed (ensures owner or admin acting on behalf)
            $data = $request->validated();
            $data['status'] = 'draft';

            $this->vendorDesignTemplateStoreService->updateStoreSettings($template, $data, $template->vendor_id, false);

            // Reload template with relationships
            $template->loadDetails();

            // Eager load the specific store override we just updated
            $template->loadStoreSpecificDetails($request->store_id);

            return $this->successResponse(
                new VendorDesignTemplateResource($template),
                'Store template saved as draft successfully.'
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to save store template as draft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'template_id' => $template->id,
                'vendor_id' => $template->vendor_id,
            ]);

            return $this->errorResponse(
                'An error occurred while saving store template as draft.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroyImage($id)
    {
        $image = VendorDesignTemplateStoreImage::with('storeTemplate.template')->find($id);

        if (! $image) {
            return $this->errorResponse('Image not found.', Response::HTTP_NOT_FOUND);
        }

        // Check authorization using the template associated with the image
        if ($image->storeTemplate && $image->storeTemplate->template) {
            $this->authorize('update', $image->storeTemplate->template);
        } else {
            return $this->errorResponse('Invalid image association.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->vendorDesignTemplateStoreService->deleteImage($image);

            return $this->successResponse(
                null,
                'Image deleted successfully.'
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to delete store template image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'image_id' => $id,
            ]);

            return $this->errorResponse(
                'An error occurred while deleting the image.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
