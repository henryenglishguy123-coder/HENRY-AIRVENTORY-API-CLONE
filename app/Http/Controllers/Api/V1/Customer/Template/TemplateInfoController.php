<?php

namespace App\Http\Controllers\Api\V1\Customer\Template;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\Template\VendorDesignTemplateResource;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Services\Template\TemplateDetailsService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TemplateInfoController extends Controller
{
    use ApiResponse;

    public function __construct(private TemplateDetailsService $templateService) {}

    /**
     * Display detailed information about a template with optimized queries.
     */
    public function show(Request $request, VendorDesignTemplate $template): JsonResponse
    {
        // Use Policy for authorization with the correct guard-resolved user
        $user = Auth::guard('customer')->check()
            ? Auth::guard('customer')->user()
            : Auth::guard('admin_api')->user();

        $this->authorizeForUser($user, 'view', $template);

        // Load template details with optimized queries
        $this->templateService->loadTemplateDetails($template);

        // If store_id is provided, load store-specific details
        if ($request->filled('store_id')) {
            $validated = $request->validate([
                'store_id' => 'required|integer',
            ]);

            $customer = Auth::guard('customer')->user();
            $store = \DB::table('vendor_connected_stores')
                ->where('id', $validated['store_id'])
                ->where('vendor_id', $customer->id)
                ->select('id')
                ->first();

            if (! $store) {
                return $this->errorResponse('Unauthorized access to store', Response::HTTP_FORBIDDEN);
            }

            // Load store-specific overrides
            $this->templateService->loadStoreDetails($template, (int) $validated['store_id']);
        }

        return $this->successResponse(
            new VendorDesignTemplateResource($template, $this->templateService)
        );
    }
}
