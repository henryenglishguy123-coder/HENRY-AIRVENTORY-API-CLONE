<?php

namespace App\Http\Controllers\Api\V1\Customer\Template;

use App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerController;
use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Models\Customer\Designer\VendorDesignLayer;
use App\Models\Customer\Designer\VendorDesignLayerImage;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateCatalogProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'store_id' => ['nullable', 'integer', 'exists:vendor_connected_stores,id'],
        ]);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $vendor = app(AccountController::class)->resolveCustomer($request);
        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        $templates = VendorDesignTemplate::query()
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('store_id'), function ($query) use ($request) {
                $query->whereHas('storeOverrides', function ($q) use ($request) {
                    $q->where('vendor_connected_store_id', $request->store_id);
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product.info', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });

                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->with([
                'designImages',
                'product.info',
                'product.children.attributes.option.attribute',
                'layers.catalogTemplateLayer:id,layer_name',
                'layers:id,catalog_design_template_layer_id,vendor_design_template_id,image_path,scale_x,scale_y,width,height,rotation_angle,position_top,position_left',
                'storeOverrides.connectedStore.storeChannel',
            ])
            ->latest()
            ->paginate($perPage);

        $assignedStores = $templates->getCollection()
            ->flatMap(
                fn (VendorDesignTemplate $template) => $template->storeOverrides?->map(fn ($override) => [
                    'id' => $override->connectedStore?->id,
                    'channel' => $override->connectedStore?->storeChannel?->code,
                    'channel_logo' => $override->connectedStore?->storeChannel?->logo_url,
                    'domain' => $override->connectedStore?->link
                        ? preg_replace('#^https?://#', '', $override->connectedStore->link)
                        : null,
                ]) ?? []
            )
            ->filter(fn ($store) => ! empty($store['id']))
            ->unique('id')
            ->values();

        $templates->through(fn (VendorDesignTemplate $template) => $this->transformTemplate($template));

        /*
        |--------------------------------------------------------------------------
        | API Pagination Response (data + links + meta)
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,
            'data' => $templates->items(),
            'stores' => $assignedStores,
            'links' => [
                'first' => $templates->url(1),
                'last' => $templates->url($templates->lastPage()),
                'prev' => $templates->previousPageUrl(),
                'next' => $templates->nextPageUrl(),
            ],

            'meta' => [
                'current_page' => $templates->currentPage(),
                'from' => $templates->firstItem(),
                'last_page' => $templates->lastPage(),
                'path' => $templates->path(),
                'per_page' => $templates->perPage(),
                'to' => $templates->lastItem(),
                'total' => $templates->total(),
            ],
        ]);
    }

    protected function transformTemplate(VendorDesignTemplate $template): array
    {
        $product = $template->product;
        $priceRange = $product?->getPriceRangeWithMargin();

        return [
            'id' => $template->id,
            'product_name' => $product?->info?->name,
            'designs' => $template->layers
                ?->pluck('catalogTemplateLayer.layer_name')
                ->filter()
                ->unique()
                ->values() ?? [],
            'price_range' => $priceRange['range'] ?? null,
            'variations' => $this->buildVariations($product),
            'temp_image' => getImageUrl($template->designImages->first()?->image),
            'stores' => $template->storeOverrides?->map(fn ($override) => [
                'id' => $override->connectedStore?->id,
                'channel' => $override->connectedStore?->storeChannel?->code,
                'channel_logo' => $override->connectedStore?->storeChannel?->logo_url,
                'domain' => $override->connectedStore?->link
                    ? preg_replace('#^https?://#', '', $override->connectedStore->link)
                    : null,
                'sync_status' => $override->sync_status,
                'sync_error' => $override->sync_error,
                'external_product_id' => $override->external_product_id,
                'is_link_only' => (bool) ($override->is_link_only ?? false),
            ]) ?? [],
        ];
    }

    protected function buildVariations($product): array
    {
        if (! $product || $product->children->isEmpty()) {
            return [];
        }

        $attributes = [];

        foreach ($product->children as $child) {
            foreach ($child->attributes as $attr) {
                $code = $attr->attribute?->attribute_code;

                if (! $code || ! $attr->option) {
                    continue;
                }

                $attributes[$code][$attr->option->option_id] = [
                    'id' => $attr->option->option_id,
                    'key' => $attr->option->key,
                    'value' => $attr->option->option_value,
                ];
            }
        }

        return collect($attributes)
            ->map(fn ($options) => array_values($options))
            ->toArray();
    }

    public function show(Request $request, VendorDesignTemplate $template)
    {
        $vendor = app(AccountController::class)->resolveCustomer($request);
        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        if ($template->vendor_id !== $vendor->id) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized access to this template'),
            ], 403);
        }
        $template->load(['product', 'layers', 'manufacturingFactory']);

        $isSynced = $template->storeOverrides()
            ->whereNotIn('sync_status', ['failed', 'disconnected'])
            ->exists();

        $designerResponse = app(ProductDesignerController::class)
            ->index($request, $template->product->slug, $template->manufacturingFactory)
            ->getData(true);

        return response()->json([
            'success' => true,
            'customer_template' => [
                'id' => $template->id,
                'vendor_id' => $template->vendor_id,
                'catalog_design_template_id' => $template->catalog_design_template_id,
                'created_at' => $template->created_at,
                'product' => $template->product,
                'is_synced' => $isSynced,
                'sync_message' => $isSynced ? __('This template is already synced with a store. You cannot edit it. Please duplicate the template to make changes.') : null,
            ],
            'applied_layers' => $template->layers->map(fn ($layer) => [
                'layer_id' => $layer->catalog_design_template_layer_id,
                'technique_id' => $layer->technique_id,
                'type' => $layer->type,
                'image' => getImageUrl($layer->image_path),
                'transform' => [
                    'scaleX' => (string) $layer->scale_x,
                    'scaleY' => (string) $layer->scale_y,
                    'width' => (string) $layer->width,
                    'height' => (string) $layer->height,
                    'angle' => (string) $layer->rotation_angle,
                    'top' => (string) $layer->position_top,
                    'left' => (string) $layer->position_left,
                ],
            ]),
            'designer_config' => $designerResponse,
        ]);
    }

    public function duplicate(Request $request, VendorDesignTemplate $template)
    {
        $vendor = app(AccountController::class)->resolveCustomer($request);
        if ($template->vendor_id !== $vendor->id) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized access to this template'),
            ], 403);
        }

        try {
            DB::beginTransaction();

            $newTemplate = $template->replicate();
            $newTemplate->save();

            $layers = VendorDesignLayer::where('vendor_design_template_id', $template->id)->get();

            foreach ($layers as $layer) {
                $newLayer = $layer->replicate();
                $newLayer->vendor_design_template_id = $newTemplate->id;
                $newLayer->save();
            }

            $images = VendorDesignLayerImage::where('template_id', $template->id)->get();
            foreach ($images as $image) {
                $newImage = $image->replicate();
                $newImage->template_id = $newTemplate->id;
                $newImage->save();
            }

            $pivot = VendorDesignTemplateCatalogProduct::where('vendor_design_template_id', $template->id)->first();
            if ($pivot) {
                $newPivot = $pivot->replicate();
                $newPivot->vendor_design_template_id = $newTemplate->id;
                $newPivot->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Template duplicated successfully.'),
                'data' => $this->transformTemplate($newTemplate->refresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => __('Failed to duplicate template'),
            ], 500);
        }
    }
}
