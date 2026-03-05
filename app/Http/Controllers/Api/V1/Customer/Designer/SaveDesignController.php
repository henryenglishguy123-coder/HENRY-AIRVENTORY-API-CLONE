<?php

namespace App\Http\Controllers\Api\V1\Customer\Designer;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignLayer;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateCatalogProduct;
use App\Services\Customer\Designer\StoreLayerImageAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SaveDesignController extends Controller
{
    public function saveDesign(Request $request, StoreLayerImageAction $storeLayerImageAction): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        $layers = $this->decodeLayers($request->input('layers'));
        $layers = $this->filterUsableLayers($layers);
        $generatedImages = $request->input('images');
        if (empty($layers)) {
            return response()->json([
                'success' => false,
                'message' => __('No valid design layers found.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $productId = $this->resolveProductId($request);
        if (! $productId) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid product.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = validator(
            [
                'product_id' => $productId,
                'customer_template_id' => $request->input('customer_template_id'),
                'template_id' => $request->input('template_id'),
                'factory_id' => $request->input('factory_id'),
                'layers' => $layers,
                'images' => $generatedImages,
            ],
            [
                'product_id' => ['required', 'integer'],
                'template_id' => ['required', 'integer', 'exists:catalog_design_template,id'],
                'factory_id' => ['nullable', 'integer', 'exists:factory_users,id'],
                'layers' => ['required', 'array', 'min:1'],
                'customer_template_id' => [
                    'nullable',
                    'integer',
                    'exists:vendor_design_templates,id',
                ],
                'layers.*.technique_id' => ['required', 'integer', 'exists:printing_techniques,id'],
                'layers.*.canvas.objects' => ['required', 'array', 'min:1'],

                'images' => ['required', 'array'],
                'images.*' => ['required', 'array'],                // layer_id
                'images.*.*' => ['required', 'array'],              // option_id
                'images.*.*.image' => ['required', 'string'],       // actual image
            ]
        )->validate();

        return $this->persistDesign($validated, $customer, $storeLayerImageAction);
    }

    /**
     * Persist design and layers safely
     */
    private function persistDesign(array $validated, $customer, StoreLayerImageAction $storeLayerImageAction): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validTemplateLayerIds = DB::table('catalog_design_template_layers')
                ->where('catalog_design_template_id', $validated['template_id'])
                ->pluck('id')->all();
            $validated['layers'] = collect($validated['layers'])
                ->filter(
                    fn ($_, $layerId) => in_array((int) $layerId, $validTemplateLayerIds, true)
                )
                ->toArray();

            if (empty($validated['layers'])) {
                throw new \DomainException('No valid template layers provided.');
            }

            /** --------------------------------
             *  CREATE OR FETCH DESIGN
             * -------------------------------- */
            if (! empty($validated['customer_template_id'])) {
                // UPDATE FLOW
                $vendorDesign = VendorDesignTemplate::where('id', $validated['customer_template_id'])
                    ->where('vendor_id', $customer->id)
                    ->firstOrFail();

                // Safety: template must match
                if ((int) $vendorDesign->catalog_design_template_id !== (int) $validated['template_id']) {
                    throw new \DomainException('Template mismatch for this design.');
                }

                // Check if the template is synced with any store
                $hasActiveSync = $vendorDesign->storeOverrides()
                    ->whereNotIn('sync_status', ['failed', 'disconnected'])
                    ->exists();

                if ($hasActiveSync) {
                    return response()->json([
                        'success' => false,
                        'code' => 'design_already_synced',
                        'message' => __('This template is already synced with a store. Please duplicate it to edit.'),
                    ], 403);
                }
            } else {
                // CREATE FLOW
                $vendorDesign = VendorDesignTemplate::create([
                    'vendor_id' => $customer->id,
                    'catalog_design_template_id' => $validated['template_id'],
                ]);
            }

            // 1. Get existing layers for this design
            $existingLayerIds = VendorDesignLayer::where('vendor_design_template_id', $vendorDesign->id)
                ->pluck('catalog_design_template_layer_id')
                ->toArray();

            // 2. Determine layers to delete (existing in DB but not in request)
            $layersToDelete = array_diff($existingLayerIds, array_keys($validated['layers']));

            if (! empty($layersToDelete)) {
                VendorDesignLayer::where('vendor_design_template_id', $vendorDesign->id)
                    ->whereIn('catalog_design_template_layer_id', $layersToDelete)
                    ->delete();
            }
            /** --------------------------------
             *  UPSERT LAYERS
             * -------------------------------- */
            foreach ($validated['layers'] as $templateLayerId => $layer) {
                $image = $this->getPrimaryImageObject($layer['canvas']['objects']);
                if (! $image) {
                    throw new \DomainException("User image missing in layer {$templateLayerId}");
                }

                $layerData = $this->mapLayerData(
                    $vendorDesign->id,
                    (int) $templateLayerId,
                    $layer,
                    $image
                );

                unset($layerData['created_at']); // allow updateOrCreate

                VendorDesignLayer::updateOrCreate(
                    [
                        'vendor_design_template_id' => $vendorDesign->id,
                        'catalog_design_template_layer_id' => (int) $templateLayerId,
                    ],
                    $layerData
                );
            }

            /** --------------------------------
             *  PRODUCT LINK
             * -------------------------------- */
            if (empty($validated['customer_template_id'])) {
                VendorDesignTemplateCatalogProduct::updateOrCreate(
                    [
                        'vendor_design_template_id' => $vendorDesign->id,
                    ],
                    [
                        'vendor_id' => $customer->id,
                        'catalog_product_id' => $validated['product_id'],
                        'factory_id' => $validated['factory_id'] ?? null,
                    ]
                );
            }

            /** --------------------------------
             *  SAVE LAYER IMAGES
             * -------------------------------- */
            $colorVariantMap = $this->getColorVariantMap($validated['product_id']);

            foreach ($validated['images'] as $layerId => $colorImages) {

                foreach ($colorImages as $colorId => $imageData) {

                    if (empty($imageData['image'])) {
                        continue;
                    }

                    // Resolve variant_id from color_id, fallback to product_id
                    $variantId = $colorVariantMap[$colorId] ?? $validated['product_id'];

                    $storeLayerImageAction->execute(
                        templateId: (int) $vendorDesign->id,
                        layerId: (int) $layerId,
                        productId: (int) $validated['product_id'],
                        variantId: (int) $variantId,
                        colorId: (int) $colorId,
                        vendorId: (int) $customer->id,
                        imagePath: $this->normalizeImagePath($imageData['image'])
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => empty($validated['customer_template_id'])
                    ? 'Design saved successfully.'
                    : 'Design updated successfully.',
                'customer_template_id' => $vendorDesign->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e instanceof \DomainException
                    ? $e->getMessage()
                    : __('Failed to save design.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Decode layers safely
     */
    private function decodeLayers($layers): array
    {
        if (is_string($layers)) {
            $decoded = json_decode($layers, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(__('Invalid layers JSON.'));
            }

            return $decoded;
        }

        return is_array($layers) ? $layers : [];
    }

    /**
     * Filter only usable layers
     */
    private function filterUsableLayers(array $layers): array
    {
        return collect($layers)->filter(fn ($layer) => ! empty($layer['technique_id']) && ! empty($layer['canvas']['objects']) && is_array($layer['canvas']['objects']))->toArray();
    }

    /**
     * Resolve product ID
     */
    private function resolveProductId(Request $request): ?int
    {
        if ($request->filled('product_id')) {
            return (int) $request->product_id;
        }
        if ($request->filled('product_slug')) {
            return DB::table('catalog_products')->where('slug', $request->product_slug)->value('id');
        }

        return null;
    }

    /**
     * Map layer data cleanly
     */
    private function mapLayerData(int $designId, int $templateLayerId, array $layer, array $image): array
    {
        $imagePath = $this->normalizeImagePath($image['src']);

        return [
            'vendor_design_template_id' => $designId,
            'catalog_design_template_layer_id' => $templateLayerId,
            'technique_id' => $layer['technique_id'],
            'image_path' => $imagePath ?? '',
            'width' => $image['width'] ?? 0,
            'height' => $image['height'] ?? 0,
            'scale_x' => $image['scaleX'] ?? 1,
            'scale_y' => $image['scaleY'] ?? 1,
            'rotation_angle' => $image['angle'] ?? 0,
            'position_top' => $image['top'] ?? 0,
            'position_left' => $image['left'] ?? 0,
            'canvas_json' => json_encode($layer['canvas']),
        ];
    }

    /**
     * Get primary image (clipPath wins, fallback otherwise)
     */
    private function getPrimaryImageObject(array $objects): ?array
    {
        $fallback = null;
        foreach ($objects as $object) {
            if (strtolower($object['type'] ?? '') === 'image' && ! empty($object['src'])) {
                if (! empty($object['clipPath'])) {
                    return $object;
                }
                $fallback ??= $object;
            }
        }

        return $fallback;
    }

    private function normalizeImagePath(string $src): string
    {
        $baseUrl = rtrim(config('filesystems.disks.s3.url'), '/');
        if (str_starts_with($src, $baseUrl)) {
            return ltrim(str_replace($baseUrl, '', $src), '/');
        }

        return ltrim(parse_url($src, PHP_URL_PATH) ?? $src, '/');
    }

    /**
     * Build map of attribute_option_id => variant_id
     */
    private function getColorVariantMap(int $productId): array
    {
        $product = CatalogProduct::with('children.attributes')->find($productId);

        $map = [];
        if ($product && $product->children->isNotEmpty()) {
            foreach ($product->children as $child) {
                foreach ($child->attributes as $attribute) {
                    // attribute_value stores the option_id for select attributes
                    $map[$attribute->attribute_value] = $child->id;
                }
            }
        }

        return $map;
    }
}
