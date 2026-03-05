<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\Product\StoreProductRequest;
use App\Models\Catalog\Attribute\CatalogAttributeOption;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductAttribute;
use App\Models\Catalog\Product\CatalogProductCategory;
use App\Models\Catalog\Product\CatalogProductFile;
use App\Models\Catalog\Product\CatalogProductInfo;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductParent;
use App\Models\Catalog\Product\CatalogProductPrice;
use App\Models\Catalog\Product\CatalogProductSuperAttribute;
use App\Models\Currency\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddProductController extends Controller
{
    /**
     * Show create product page.
     */
    public function addProduct()
    {
        $defaultCurrency = Currency::getDefaultCurrency();
        $attributes = \App\Models\Catalog\Attribute\CatalogAttribute::query()
            ->active()
            ->with([
                'description:attribute_id,name',
                'options:option_id,attribute_id,option_value,key,type',
            ])
            ->orderBy('attribute_id', 'asc')
            ->get();

        return view('admin.catalog.product.create', compact('defaultCurrency', 'attributes'));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $product = null;
        try {
            DB::transaction(function () use ($validated, $request, &$product) {
                if (! empty($validated['variants'])) {
                    $this->validateVariantOptionIds($validated['variants']);
                }
                $slug = $this->generateUniqueSlug($validated['name']);
                $product = CatalogProduct::create([
                    'sku' => $validated['sku'],
                    'type' => 'configurable',
                    'weight' => $validated['weight'] ?? null,
                    'status' => $validated['status'],
                    'slug' => $slug,
                ]);
                CatalogProductInventory::create([
                    'product_id' => $product->id,
                    'stock_status' => $validated['stock_status'],
                    'quantity' => $validated['quantity'] ?? 0,
                    'manage_inventory' => $validated['manage_inventory'] ?? 0,
                ]);
                CatalogProductInfo::create([
                    'catalog_product_id' => $product->id,
                    'name' => $validated['name'],
                    'short_description' => $validated['short_description'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'meta_title' => $validated['meta_title'] ?? null,
                    'meta_description' => $validated['meta_description'] ?? null,
                ]);
                CatalogProductPrice::create([
                    'catalog_product_id' => $product->id,
                    'regular_price' => $validated['price'],
                    'sale_price' => $validated['sale_price'] ?? null,
                ]);
                $this->attachCategoriesRecursive($product->id, $validated['category_id']);
                $superAttributeIds = $this->collectSuperAttributeIds($validated['variants'] ?? []);
                if (! empty($superAttributeIds)) {
                    $rows = [];
                    foreach ($superAttributeIds as $attrId) {
                        $rows[] = ['product_id' => $product->id, 'attribute_id' => $attrId];
                    }
                    CatalogProductSuperAttribute::insert($rows);
                }
                if (! empty($validated['attributes'])) {
                    $this->saveProductAttributes($product->id, $validated['attributes']);
                }
                $gallery = ! empty($validated['gallery']) ? json_decode($validated['gallery'], true) : [];
                if (! empty($gallery)) {
                    $this->saveGallery($product->id, $gallery);
                }
                if (! empty($validated['variants'])) {
                    $this->saveVariants($product->id, $validated['variants'], $request, $validated);
                }
            }, 5);

            return response()->json([
                'success' => true,
                'message' => __('Product added successfully.'),
                'product_id' => $product->id,
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            // Log the exception with context for debugging
            Log::error('Failed to create product', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate unique slug for a product name.
     */
    private function generateUniqueSlug(string $name, bool $short = false): string
    {
        $base = Str::slug($name);
        if ($short) {
            $base = Str::limit($base, 120, '');
        }
        $slug = $base;
        $count = 1;
        while (CatalogProduct::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Attach category and its parent chain to product.
     */
    private function attachCategoriesRecursive(int $productId, int $categoryId): void
    {
        $productCategories = [
            ['catalog_product_id' => $productId, 'catalog_category_id' => $categoryId],
        ];

        // Recursive CTE to get parent categories (same as original approach)
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
            $productCategories[] = ['catalog_product_id' => $productId, 'catalog_category_id' => $parentId];
        }
        CatalogProductCategory::insert($productCategories);
    }

    /**
     * Save product-level attributes.
     */
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

    /**
     * Save gallery files for product. Assumes gallery is decoded array with 'file_path' keys.
     */
    private function saveGallery(int $productId, array $gallery): void
    {
        $baseUrl = Storage::url('/');
        foreach ($gallery as $index => $image) {
            $filePath = $image['file_path'] ?? null;
            if (! $filePath) {
                continue;
            }
            $relativePath = Str::startsWith($filePath, $baseUrl) ? Str::replaceFirst($baseUrl, '', $filePath) : $filePath;
            CatalogProductFile::create([
                'catalog_product_id' => $productId,
                'image' => $relativePath,
                'type' => $image['type'] ?? 'image',
                'order' => $index + 1,
            ]);
        }
    }

    private function saveVariants(int $parentProductId, array $variants, $request, array $parentValidated): void
    {
        foreach ($variants as $index => $variantData) {
            $variantName = $variantData['name'] ?? ($parentValidated['name'].' - '.($variantData['sku'] ?? 'variant'));
            $slugBase = $variantData['sku'] ?? $variantName;
            $variantSlug = $this->generateUniqueSlug($slugBase, true);
            $variantProduct = CatalogProduct::create([
                'sku' => $variantData['sku'],
                'type' => 'simple',
                'weight' => $variantData['weight'] ?? $parentValidated['weight'] ?? null,
                'status' => $variantData['status'] ?? 1,
                'slug' => $variantSlug,
            ]);

            // Inventory for variant
            CatalogProductInventory::create([
                'product_id' => $variantProduct->id,
                'stock_status' => $variantData['stock_status'] ?? $parentValidated['stock_status'],
                'quantity' => $variantData['stock'] ?? 0,
                'manage_inventory' => $variantData['manage_inventory'] ?? $parentValidated['manage_inventory'],
            ]);

            // Info for variant
            CatalogProductInfo::create([
                'catalog_product_id' => $variantProduct->id,
                'name' => $variantName,
                'short_description' => $variantData['short_description'] ?? null,
                'description' => $variantData['description'] ?? null,
                'meta_title' => $variantName,
                'meta_description' => $variantData['meta_description'] ?? null,
            ]);

            // Variant attributes (e.g., size => option_id)
            if (! empty($variantData['attributes']) && is_array($variantData['attributes'])) {
                $variantAttrRows = [];
                foreach ($variantData['attributes'] as $attrId => $option) {
                    if ($option === null || $option === '') {
                        continue;
                    }
                    $variantAttrRows[] = [
                        'catalog_product_id' => $variantProduct->id,
                        'catalog_attribute_id' => $attrId,
                        'attribute_value' => $option,
                    ];
                }
                if (! empty($variantAttrRows)) {
                    CatalogProductAttribute::insert($variantAttrRows);
                }
            }

            if ($request->hasFile("variants.{$index}.image")) {
                $file = $request->file("variants.{$index}.image");
                $path = $file->store('catalog/product/'.date('Y/m'), config('filesystems.default', 's3'));
                CatalogProductFile::create([
                    'catalog_product_id' => $variantProduct->id,
                    'image' => $path,
                    'type' => 'image',
                    'order' => 1,
                ]);
            }
            CatalogProductParent::create([
                'catalog_product_id' => $variantProduct->id,
                'parent_id' => $parentProductId,
            ]);
        }
    }

    /**
     * Collect unique attribute IDs used by variants.
     */
    private function collectSuperAttributeIds(array $variants): array
    {
        $ids = [];
        foreach ($variants as $variant) {
            if (! empty($variant['attributes']) && is_array($variant['attributes'])) {
                $ids = array_merge($ids, array_keys($variant['attributes']));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Validate that option ids referenced by variants actually exist in DB.
     *
     * @throws \Exception
     */
    private function validateVariantOptionIds(array $variants): void
    {
        $submittedOptionIds = [];
        foreach ($variants as $variant) {
            if (isset($variant['attributes']) && is_array($variant['attributes'])) {
                foreach ($variant['attributes'] as $optionId) {
                    $submittedOptionIds[] = $optionId;
                }
            }
        }
        $submittedOptionIds = array_filter(array_unique($submittedOptionIds), function ($v) {
            return $v !== null && $v !== '';
        });
        if (! empty($submittedOptionIds)) {
            $existingCount = CatalogAttributeOption::whereIn('option_id', $submittedOptionIds)->count();
            if ($existingCount !== count($submittedOptionIds)) {
                throw new \Exception(__('One or more selected variation options are invalid or do not exist.'));
            }
        }
    }
}
