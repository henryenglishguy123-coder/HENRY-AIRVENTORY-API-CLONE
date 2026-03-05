<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\Product\UpdateProductRequest;
use App\Models\Catalog\Attribute\CatalogAttribute;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductAttribute;
use App\Models\Catalog\Product\CatalogProductCategory;
use App\Models\Catalog\Product\CatalogProductFile;
use App\Models\Catalog\Product\CatalogProductInfo;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductParent;
use App\Models\Catalog\Product\CatalogProductPrice;
use App\Models\Currency\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EditProductController extends Controller
{
    public function edit(int $productId)
    {
        $product = CatalogProduct::with([
            'info',
            'inventory',
            'prices',
            'categories',
            'files',
            'attributes',
            'children.info',
            'children.inventory',
            'children.attributes',
            'children.factories', // Load factories
        ])->findOrFail($productId);

        $defaultCurrency = Currency::getDefaultCurrency();
        $attributes = CatalogAttribute::query()
            ->active()
            ->with([
                'description:attribute_id,name',
                'options:option_id,attribute_id,option_value,key,type',
            ])
            ->orderBy('attribute_id', 'asc')
            ->get();

        $basePrice = $product->prices->whereNull('factory_id')->first();
        $inventory = $product->inventory;
        $primaryCategoryId = $this->determinePrimaryCategoryId($product);

        $initialGallery = $product->files->map(function (CatalogProductFile $file) {
            return [
                'file_path' => Storage::url($file->image),
                'type' => $file->type,
            ];
        })->values()->all();

        $attributeValues = $product->attributes
            ->groupBy('catalog_attribute_id')
            ->map(function ($items) {
                return $items->pluck('attribute_value')->all();
            })
            ->toArray();

        $existingVariants = [];
        $variantAttributesSelections = [];

        $variationAttributeIds = $attributes
            ->where('use_for_variation', 1)
            ->pluck('attribute_id')
            ->all();
        $product->loadMissing(['children.files']);

        foreach ($product->children as $child) {
            $attrMap = [];
            foreach ($child->attributes as $attr) {
                if (! in_array($attr->catalog_attribute_id, $variationAttributeIds, true)) {
                    continue;
                }
                $attrMap[$attr->catalog_attribute_id] = $attr->attribute_value;
                $variantAttributesSelections[$attr->catalog_attribute_id][] = $attr->attribute_value;
            }

            $childInventory = $child->inventory;
            $childImage = optional($child->files->first())->image;

            $existingVariants[] = [
                'id' => $child->id,
                'sku' => $child->sku,
                'name' => optional($child->info)->name,
                'stock' => $childInventory && (int) $childInventory->manage_inventory === 1
                    ? (int) $childInventory->quantity
                    : null,
                'attributes' => $attrMap,
                'image' => $childImage ? Storage::url($childImage) : null,
                'factories_count' => $child->factories->count(),
            ];
        }

        $variantAttributesSelections = array_map(function ($values) {
            return array_values(array_unique($values));
        }, $variantAttributesSelections);

        return view('admin.catalog.product.edit', compact(
            'product',
            'defaultCurrency',
            'attributes',
            'basePrice',
            'inventory',
            'primaryCategoryId',
            'attributeValues',
            'initialGallery',
            'existingVariants',
            'variantAttributesSelections'
        ));
    }

    public function update(UpdateProductRequest $request, CatalogProduct $product): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $product) {
                $product->update([
                    'sku' => $validated['sku'],
                    'weight' => $validated['weight'] ?? null,
                    'status' => $validated['status'],
                ]);

                CatalogProductInfo::updateOrCreate(
                    ['catalog_product_id' => $product->id],
                    [
                        'name' => $validated['name'],
                        'short_description' => $validated['short_description'] ?? null,
                        'description' => $validated['description'] ?? null,
                        'meta_title' => $validated['meta_title'] ?? null,
                        'meta_description' => $validated['meta_description'] ?? null,
                    ]
                );

                $inventory = $product->inventory ?: new CatalogProductInventory([
                    'product_id' => $product->id,
                ]);

                $inventory->stock_status = $validated['stock_status'];
                $inventory->manage_inventory = $validated['manage_inventory'] ?? 0;
                $inventory->quantity = $validated['quantity'] ?? 0;
                $inventory->save();

                CatalogProductPrice::updateOrCreate(
                    [
                        'catalog_product_id' => $product->id,
                        'factory_id' => null,
                    ],
                    [
                        'regular_price' => $validated['price'],
                        'sale_price' => $validated['sale_price'] ?? null,
                    ]
                );

                CatalogProductCategory::where('catalog_product_id', $product->id)->delete();
                $this->attachCategoriesRecursive($product->id, $validated['category_id']);

                CatalogProductAttribute::where('catalog_product_id', $product->id)->delete();
                if (! empty($validated['attributes']) && is_array($validated['attributes'])) {
                    $this->saveProductAttributes($product->id, $validated['attributes']);
                }

                if (! empty($validated['gallery'])) {
                    $gallery = json_decode($validated['gallery'], true) ?: [];

                    $currentGalleryImages = CatalogProductFile::where('catalog_product_id', $product->id)
                        ->pluck('image')
                        ->toArray();

                    $newGalleryImages = array_map(function ($item) {
                        return $item['file_path'] ?? '';
                    }, $gallery);

                    // Simple cleanup: if an image is in DB but not in new gallery (by matching paths), delete it?
                    // Note: 'file_path' in gallery input might be full URL or relative.
                    // saveGallery handles the conversion. We should rely on saveGallery insertion,
                    // but we must delete the physical files that are Abandoned.
                    // However, comparing partial paths is tricky.
                    // A safer approach for "Replace All":
                    // 1. Identify files NOT in the new list.
                    // BUT $item['file_path'] from frontend might differ from stored relative path.
                    // Let's implement the deletion of ALL OLD files that are NOT in the new list would require normalizing paths.
                    // Given the ambiguity, I'll delete ALL physical files associated with the product that are not reused?
                    // No, that's dangerous.
                    // I will implement the Copilot simple suggestion: Delete *all* physical files associated with the records being deleted?
                    // Copilot said "The deletion of gallery files on line 176 doesn't clean up... Add logic to delete..."
                    // If I delete logic, I simply remove the files.
                    // BUT if the user KEPT an image, I shouldn't delete the physical file!
                    // The Frontend sends the KEPT images back in the gallery array.
                    // So if I delete the physical file, the new record will point to a non-existent file.
                    // FATAL FLAW in Copilot's suggestion if taken literally.
                    // CORRECT LOGIC: Delete files that are IN $currentGalleryImages AND NOT IN $gallery (normalized).

                    // Normalize new gallery paths for comparison (remove storage URL if present)
                    $baseUrl = Storage::url('/');
                    $keptPaths = [];
                    foreach ($gallery as $gItem) {
                        $path = $gItem['file_path'] ?? '';
                        if (Str::startsWith($path, $baseUrl)) {
                            $path = Str::replaceFirst($baseUrl, '', $path);
                        }
                        $keptPaths[] = $path;
                    }

                    foreach ($currentGalleryImages as $oldImage) {
                        if (! in_array($oldImage, $keptPaths)) {
                            if ($oldImage) {
                                try {
                                    Storage::disk(config('filesystems.default', 's3'))->delete($oldImage);
                                } catch (\Throwable $e) {
                                    Log::warning('Failed to delete old gallery image', ['path' => $oldImage]);
                                }
                            }
                        }
                    }

                    CatalogProductFile::where('catalog_product_id', $product->id)->delete();
                    if (! empty($gallery)) {
                        $this->saveGallery($product->id, $gallery);
                    }
                }
            }, 5);

            Cache::store(config('cache.catalog_store'))->forget("admin_product_factory_info_{$product->id}");

            return response()->json([
                'success' => true,
                'message' => __('Product updated successfully.'),
                'product_id' => $product->id,
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Failed to update product', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('An internal server error occurred.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Removed syncVariants and createVariant methods as they are no longer used.

    private function deleteVariants(array $variantIds): void
    {
        if (empty($variantIds)) {
            return;
        }

        $files = CatalogProductFile::whereIn('catalog_product_id', $variantIds)->pluck('image')->all();
        foreach ($files as $imagePath) {
            if ($imagePath) {
                Storage::disk(config('filesystems.default', 's3'))->delete($imagePath);
            }
        }

        CatalogProductAttribute::whereIn('catalog_product_id', $variantIds)->delete();
        CatalogProductFile::whereIn('catalog_product_id', $variantIds)->delete();
        CatalogProductInventory::whereIn('product_id', $variantIds)->delete();
        CatalogProductInfo::whereIn('catalog_product_id', $variantIds)->delete();
        CatalogProductParent::whereIn('catalog_product_id', $variantIds)->delete();
        CatalogProduct::whereIn('id', $variantIds)->delete();
    }

    private function generateUniqueSlug(string $name, bool $short = false): string
    {
        $base = Str::slug($name);
        if ($short) {
            $base = Str::limit($base, 120, '');
        }
        $slug = $base;
        $count = 1;
        $attempts = 0;
        $maxAttempts = 100;

        while (CatalogProduct::where('slug', $slug)->exists()) {
            if ($attempts >= $maxAttempts) {
                // Fallback to a unique suffix if finding a simple numbered slug fails
                return $base.'-'.uniqid();
            }
            $slug = "{$base}-{$count}";
            $count++;
            $attempts++;
        }

        return $slug;
    }

    private function attachCategoriesRecursive(int $productId, int $categoryId): void
    {
        $productCategories = [
            ['catalog_product_id' => $productId, 'catalog_category_id' => $categoryId],
        ];

        $categoryRows = DB::select('
            WITH RECURSIVE category_hierarchy AS (
                SELECT id, parent_id FROM catalog_categories WHERE id = ?
                UNION ALL
                SELECT c.id, c.parent_id FROM catalog_categories c
                INNER JOIN category_hierarchy ch ON c.id = ch.parent_id
            )
            SELECT id FROM category_hierarchy WHERE id != ?
        ', [$categoryId, $categoryId]);

        $parentIds = array_column($categoryRows, 'id');

        foreach ($parentIds as $parentId) {
            $productCategories[] = [
                'catalog_product_id' => $productId,
                'catalog_category_id' => $parentId,
            ];
        }

        CatalogProductCategory::insert($productCategories);
    }

    private function saveProductAttributes(int $productId, array $attributes): void
    {
        $rows = [];

        foreach ($attributes as $attributeId => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $rows[] = [
                        'catalog_product_id' => $productId,
                        'catalog_attribute_id' => $attributeId,
                        'attribute_value' => $value,
                    ];
                }
            } else {
                if ($values === null || $values === '') {
                    continue;
                }
                $rows[] = [
                    'catalog_product_id' => $productId,
                    'catalog_attribute_id' => $attributeId,
                    'attribute_value' => $values,
                ];
            }
        }

        if (! empty($rows)) {
            CatalogProductAttribute::insert($rows);
        }
    }

    private function saveGallery(int $productId, array $gallery): void
    {
        $baseUrl = Storage::url('/');
        $rows = [];

        foreach ($gallery as $index => $image) {
            $filePath = $image['file_path'] ?? null;
            if (! $filePath) {
                continue;
            }

            $relativePath = Str::startsWith($filePath, $baseUrl)
                ? Str::replaceFirst($baseUrl, '', $filePath)
                : $filePath;

            $rows[] = [
                'catalog_product_id' => $productId,
                'image' => $relativePath,
                'type' => $image['type'] ?? 'image',
                'order' => $index + 1,
            ];
        }

        if (! empty($rows)) {
            CatalogProductFile::insert($rows);
        }
    }

    public function updateVariantImage(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => 'required|exists:catalog_products,id',
            'image' => 'required|image|max:2048',
        ]);

        try {
            $variant = CatalogProduct::findOrFail($request->variant_id);
            $file = $request->file('image');
            $path = $file->store('catalog/product/'.date('Y/m'), config('filesystems.default', 's3'));

            // Remove old image if exists
            $oldFiles = CatalogProductFile::where('catalog_product_id', $variant->id)->get();
            foreach ($oldFiles as $oldFile) {
                if ($oldFile->image) {
                    try {
                        Storage::disk(config('filesystems.default', 's3'))->delete($oldFile->image);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to delete old variant image file', ['path' => $oldFile->image, 'error' => $e->getMessage()]);
                    }
                }
            }
            CatalogProductFile::where('catalog_product_id', $variant->id)->delete();

            CatalogProductFile::create([
                'catalog_product_id' => $variant->id,
                'image' => $path,
                'type' => 'image',
                'order' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Variant image updated successfully.'),
                'image_url' => Storage::url($path),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update variant image', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => __('Failed to update variant image')], 500);
        }
    }

    public function deleteVariant(CatalogProduct $variant): JsonResponse
    {
        try {
            // Ensure it is actually a variant (has parent)
            if (! $variant->parents()->exists()) {
                return response()->json(['success' => false, 'message' => __('Invalid variant.')], 400);
            }

            $this->deleteVariants([$variant->id]);

            return response()->json([
                'success' => true,
                'message' => __('Variant deleted successfully.'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete variant', ['variant_id' => $variant->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => __('Failed to delete variant')], 500);
        }
    }

    private function determinePrimaryCategoryId(CatalogProduct $product): ?int
    {
        $categories = $product->categories;

        if ($categories->isEmpty()) {
            return null;
        }

        $leafCandidates = $categories->filter(function ($category) use ($categories) {
            return ! $categories->contains('parent_id', $category->id);
        });

        $selected = $leafCandidates->first() ?? $categories->first();

        return $selected->id;
    }
}
