<?php

namespace App\Http\Controllers\Api\V1\Customer\Template;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Models\Customer\Designer\VendorDesignTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TemplateActionController extends Controller
{
    public function destroy(Request $request, int $templateId)
    {
        $vendor = app(AccountController::class)->resolveCustomer($request);
        $template = VendorDesignTemplate::where('vendor_id', $vendor->id)
            ->where('id', $templateId)
            ->with(['layers', 'orderItems'])
            ->first();

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => __('Template not found or access denied.'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($template->orderItems->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'code' => 'template_has_orders',
                'message' => __('This template is associated with existing orders and cannot be deleted.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasActiveStoreSync = $template->storeOverrides()
            ->whereNotIn('sync_status', ['failed', 'disconnected'])
            ->exists();

        if ($hasActiveStoreSync) {
            return response()->json([
                'success' => false,
                'code' => 'template_has_store_sync',
                'message' => __('This template is synced with one or more stores and cannot be deleted.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            /** -----------------------------
             *  DELETE LAYER IMAGES
             * ----------------------------- */
            foreach ($template->layers as $layer) {
                if (! empty($layer->image_path)) {
                    Storage::delete($layer->image_path);
                }
            }
            /** -----------------------------
             *  DELETE TEMPLATE
             * ----------------------------- */
            $template->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Template deleted successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to delete customer template', [
                'template_id' => $templateId,
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to delete template.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
