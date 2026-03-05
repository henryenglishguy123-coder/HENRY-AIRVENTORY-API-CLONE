<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProductDesignTemplateImageController extends Controller
{
    public function assignImage(Request $request, $productId)
    {
        if (! auth()->guard('admin')->check()) {
            abort(403, __('Unauthorized'));
        }
        $product = CatalogProduct::where('id', $productId)
            ->where('status', 1)
            ->with('layerImages')
            ->whereHas('designTemplate.catalogDesignTemplate.layers')
            ->whereHas('printingPrices.layer')
            ->where('type', 'configurable')
            ->firstOrFail();

        $product->load('printingPrices.layer');
        $layers = $product->printingPrices->pluck('layer')->unique('id')->values();

        return view('admin.catalog.product.design-template.image-assign', compact('product', 'layers'));
    }

    public function uploadLayerImage(Request $request, $productId)
    {
        if (! auth()->guard('admin')->check()) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }
        $request->validate([
            'layer_id' => 'required|integer|exists:catalog_design_template_layers,id',
            'option_id' => 'required|exists:catalog_attribute_options,option_id',
            'image' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp,avif',
                'max:20480',
            ],
        ]);
        $image = $request->file('image');
        $imageInfo = getimagesize($image->getRealPath());
        $product = CatalogProduct::findOrFail($productId);
        if ($imageInfo === false) {
            throw ValidationException::withMessages([
                'image' => [__('Invalid image file.')],
            ]);
        }
        [$width, $height] = $imageInfo;
        if ($width !== $height) {
            throw ValidationException::withMessages([
                'image' => [__('Image must be square (1:1 aspect ratio). Uploaded image is '.$width.'x'.$height.'.')],
            ]);
        }
        try {
            DB::beginTransaction();
            $existing = $product->layerImages()
                ->where('catalog_design_template_layer_id', $request->layer_id)
                ->where('catalog_attribute_option_id', $request->option_id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->image_path && Storage::exists($existing->image_path)) {
                try {
                    Storage::delete($existing->image_path);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old layer image: '.$e->getMessage());
                }
            }
            $ext = $request->file('image')->getClientOriginalExtension();
            $fileName = sprintf(
                'product_%s_layer_%s_option_%s_%s.%s',
                $productId,
                $request->layer_id,
                $request->option_id,
                time(),
                $ext
            );
            $folder = "catalog/product/{$productId}/layers";
            $storageOptions = [];
            if (config('filesystems.default') === 's3') {
                $storageOptions = [
                    'CacheControl' => 'max-age=31536000, public',
                    'Expires' => gmdate('D, d M Y H:i:s \G\M\T', strtotime('+1 year')),
                ];
            }
            try {
                $path = Storage::putFileAs(
                    $folder,
                    $image,
                    $fileName,
                    $storageOptions
                );
            } catch (\Exception $e) {
                Log::error('Failed to store layer image: '.$e->getMessage());
                throw ValidationException::withMessages([
                    'image' => ['Failed to upload image. Please try again.'],
                ]);
            }
            if (! $path) {
                throw ValidationException::withMessages([
                    'image' => ['Failed to save image. Please try again.'],
                ]);
            }
            $record = $product->layerImages()->updateOrCreate(
                [
                    'catalog_design_template_layer_id' => $request->layer_id,
                    'catalog_attribute_option_id' => $request->option_id,
                ],
                [
                    'image_path' => $path,
                ]
            );
            DB::commit();

            return response()->json([
                'success' => true,
                'path' => $path,
                'id' => $record->id,
            ], Response::HTTP_OK);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Layer image upload failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while uploading the image. Please try again.'),
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function designTemplateColors(string $slug): JsonResponse
    {
        $product = $this->fetchProductWithRelations($slug);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => __('Product not found or inactive.'),
            ], Response::HTTP_NOT_FOUND);
        }

        $colors = $this->buildColorOptions($product);

        return response()->json([
            'success' => true,
            'data' => [
                'colors' => $colors,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Fetch product with optimized eager loading
     */
    private function fetchProductWithRelations(string $slug): ?CatalogProduct
    {
        return CatalogProduct::query()
            ->select([
                'catalog_products.id',
                'catalog_products.status',
                'catalog_products.type',
            ])
            ->whereHas('designTemplate')
            ->where('slug', $slug)
            ->where('status', 1)
            ->where('type', 'configurable')
            ->with([
                'children' => function ($query) {
                    $query->select([
                        'catalog_products.id',
                        'catalog_products.status',
                    ])
                        ->where('status', 1);
                },
                'children.attributes' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'catalog_attribute_id',
                        'attribute_value',
                    ]);
                },
                'children.attributes.attribute' => function ($query) {
                    $query->select([
                        'attribute_id',
                        'attribute_code',
                        'field_type',
                    ]);
                },
                'children.attributes.attribute.description' => function ($query) {
                    $query->select([
                        'attribute_id',
                        'name',
                    ]);
                },
                'children.attributes.option' => function ($query) {
                    $query->select([
                        'option_id',
                        'key',
                        'option_value',
                        'type',
                    ]);
                },
            ])
            ->first();
    }

    private function buildColorOptions(CatalogProduct $product): array
    {
        $colors = [];

        foreach ($product->children ?? [] as $child) {
            foreach ($child->attributes ?? [] as $attribute) {
                $attributeModel = $attribute->attribute ?? null;
                $option = $attribute->option ?? null;

                if (! $attributeModel || ! $option) {
                    continue;
                }

                if ($attributeModel->attribute_code !== 'color') {
                    continue;
                }

                $colorId = $option->option_id ?? null;

                if (! $colorId || isset($colors[$colorId])) {
                    continue;
                }

                $colors[$colorId] = [
                    'id' => $colorId,
                    'label' => $option->key ?? '',
                ];
            }
        }

        return array_values($colors);
    }
}
