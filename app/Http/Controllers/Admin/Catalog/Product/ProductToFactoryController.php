<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductPrice;
use App\Models\Factory\Factory;
use App\Services\StoreConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProductToFactoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('q');

        $factories = Factory::query()
            ->verified()
            ->with('business:id,factory_id,company_name')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->whereHas('business', function ($q) use ($search) {
                        $q->where('company_name', 'like', "%{$search}%");
                    })
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                }
            })
            ->select('id', 'first_name', 'last_name')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'results' => $factories->map(fn ($factory) => [
                'id' => $factory->id,
                'text' => optional($factory->business)->company_name ?? trim("{$factory->first_name} {$factory->last_name}"),
            ]),
            'pagination' => [
                'more' => $factories->hasMorePages(),
            ],
        ], Response::HTTP_OK);
    }

    public function info(Request $request, int $productId)
    {
        $cacheKey = "admin_product_factory_info_{$productId}";
        $store = Cache::store(config('cache.catalog_store'));

        $payload = $store->remember(
            $cacheKey,
            now()->addSeconds(30),
            function () use ($productId) {
                return $this->buildProductFactoryInfoPayload($productId);
            }
        );

        return response()->json($payload, Response::HTTP_OK);
    }

    private function buildProductFactoryInfoPayload(int $productId): array
    {
        $product = $this->loadProductWithRelations($productId);

        $manageInventory = (bool) optional($product->inventory)->manage_inventory;
        $basePrice = $product->prices->whereNull('factory_id')->first();
        $factoryAssignments = $this->buildFactoryAssignments($product, $manageInventory);
        $variants = $this->buildVariantPayload($product, $manageInventory);

        return [
            'product' => [
                'id' => $product->id,
                'name' => optional($product->info)->name,
                'sku' => $product->sku,
            ],
            'manage_inventory' => $manageInventory,
            'base_price' => [
                'regular_price' => optional($basePrice)->regular_price,
                'sale_price' => optional($basePrice)->sale_price,
            ],
            'inventory' => [
                'quantity' => $manageInventory
                    ? optional($product->inventory)->quantity
                    : null,
                'stock_status' => optional($product->inventory)->stock_status,
            ],
            'markup' => (float) app(StoreConfigService::class)->get('profit_global_markup', 0),
            'variants' => $variants,
            'factory_assignments' => $factoryAssignments,
        ];
    }

    private function loadProductWithRelations(int $productId): CatalogProduct
    {
        return CatalogProduct::with([
            'info:id,catalog_product_id,name',
            'inventory:id,product_id,manage_inventory,quantity,stock_status',
            'prices',
            'children.info:id,catalog_product_id,name',
            'children.inventory:id,product_id,manage_inventory,quantity,stock_status',
            'children.prices',
        ])->findOrFail($productId);
    }

    private function buildFactoryAssignments(CatalogProduct $product, bool $manageInventory): array
    {
        $childIds = $product->children->pluck('id');

        $variantFactoryPrices = CatalogProductPrice::whereIn('catalog_product_id', $childIds)
            ->whereNotNull('factory_id')
            ->get()
            ->groupBy('factory_id');

        if ($variantFactoryPrices->isEmpty()) {
            return [];
        }

        $variantFactoryInventories = CatalogProductInventory::whereIn('product_id', $childIds)
            ->whereNotNull('factory_id')
            ->get()
            ->groupBy('factory_id')
            ->map(function ($items) {
                return $items->keyBy('product_id');
            });

        $factories = Factory::with('business:id,factory_id,company_name')
            ->whereIn('id', $variantFactoryPrices->keys())
            ->get()
            ->keyBy('id');

        $factoryAssignments = [];

        foreach ($variantFactoryPrices as $factoryId => $pricesForFactory) {
            $factory = $factories->get($factoryId);
            $variantsForFactory = [];

            foreach ($pricesForFactory as $price) {
                $variantId = $price->catalog_product_id;
                $inventory = optional($variantFactoryInventories->get($factoryId))->get($variantId);

                $variantsForFactory[$variantId] = [
                    'regular_price' => $price->regular_price,
                    'sale_price' => $price->sale_price,
                    'quantity' => $manageInventory ? optional($inventory)->quantity : null,
                    'stock_status' => optional($inventory)->stock_status,
                ];
            }

            $samplePrice = $pricesForFactory->first();

            $factoryName = null;
            if ($factory) {
                $factoryName = optional($factory->business)->company_name;
                if (! $factoryName) {
                    $factoryName = trim("{$factory->first_name} {$factory->last_name}");
                }
            }

            $factoryAssignments[$factoryId] = [
                'id' => $factoryId,
                'name' => $factoryName ?: "Factory #{$factoryId}",
                'markup' => $samplePrice->specific_markup,
                'variants' => $variantsForFactory,
            ];
        }

        return $factoryAssignments;
    }

    private function buildVariantPayload(CatalogProduct $product, bool $manageInventory)
    {
        $product->loadMissing([
            'children.attributes.attribute.description',
            'children.attributes.option',
        ]);

        return $product->children->map(function ($variant) use ($manageInventory) {
            $price = $variant->prices->whereNull('factory_id')->first();
            $inventory = $variant->inventory;

            $attributesText = $variant->attributes->map(function ($attr) {
                $name = optional(optional($attr->attribute)->description)->name;
                $value = optional($attr->option)->key ?? $attr->attribute_value;

                return $name ? "{$name}: {$value}" : $value;
            })->implode(' - ');

            return [
                'id' => $variant->id,
                'name' => optional($variant->info)->name,
                'attributes_text' => $attributesText ?: optional($variant->info)->name,
                'sku' => $variant->sku,
                'regular_price' => optional($price)->regular_price,
                'sale_price' => optional($price)->sale_price,
                'quantity' => $manageInventory
                    ? optional($inventory)->quantity
                    : null,
                'stock_status' => optional($inventory)->stock_status,
            ];
        });
    }

    public function assignFactories(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:catalog_products,id'],
            'factories' => ['required', 'array'],
            'factories.*.markup' => ['nullable', 'numeric', 'min:0'],
            'factories.*.variants' => ['required', 'array'],
            'factories.*.variants.*.regular_price' => ['required', 'numeric', 'min:0'],
            'factories.*.variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'factories.*.variants.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'factories.*.variants.*.stock_status' => ['nullable', 'in:1,2'],
        ]);

        $product = CatalogProduct::with([
            'inventory:product_id,manage_inventory',
            'children:id',
        ])->findOrFail($data['product_id']);

        $manageInventory = (bool) optional($product->inventory)->manage_inventory;

        DB::beginTransaction();

        try {
            $validVariantIds = $product->children->pluck('id')->toArray();

            $incomingPairs = [];

            foreach ($data['factories'] as $factoryId => $factoryData) {

                foreach ($factoryData['variants'] as $variantId => $variantData) {

                    if (! in_array($variantId, $validVariantIds)) {
                        throw ValidationException::withMessages([
                            'variants' => "Invalid variant ID {$variantId}",
                        ]);
                    }

                    // Track valid pairs
                    $incomingPairs[] = [
                        'catalog_product_id' => $variantId,
                        'factory_id' => $factoryId,
                    ];

                    CatalogProductPrice::updateOrCreate(
                        [
                            'catalog_product_id' => $variantId,
                            'factory_id' => $factoryId,
                        ],
                        [
                            'regular_price' => $variantData['regular_price'],
                            'sale_price' => $variantData['sale_price'] ?? null,
                            'specific_markup' => (! isset($factoryData['markup']) || $factoryData['markup'] === '')
                                ? null
                                : $factoryData['markup'],
                        ]
                    );

                    CatalogProductInventory::updateOrCreate(
                        [
                            'product_id' => $variantId,
                            'factory_id' => $factoryId,
                        ],
                        [
                            'manage_inventory' => $manageInventory ? 1 : 0,
                            'quantity' => $variantData['quantity'] ?? null,
                            'stock_status' => $variantData['stock_status'] ?? 1,
                        ]
                    );
                }
            }

            /**
             * 🔥 REMOVE OLD ENTRIES NOT IN REQUEST
             */
            $variantIds = $validVariantIds;

            CatalogProductPrice::whereIn('catalog_product_id', $variantIds)
                ->whereNot(function ($query) use ($incomingPairs) {
                    foreach ($incomingPairs as $pair) {
                        $query->orWhere(function ($q) use ($pair) {
                            $q->where('catalog_product_id', $pair['catalog_product_id'])
                                ->where('factory_id', $pair['factory_id']);
                        });
                    }
                })
                ->delete();

            CatalogProductInventory::whereIn('product_id', $variantIds)
                ->whereNot(function ($query) use ($incomingPairs) {
                    foreach ($incomingPairs as $pair) {
                        $query->orWhere(function ($q) use ($pair) {
                            $q->where('product_id', $pair['catalog_product_id'])
                                ->where('factory_id', $pair['factory_id']);
                        });
                    }
                })
                ->delete();

            DB::commit();

            Cache::store(config('cache.catalog_store'))->forget("admin_product_factory_info_{$product->id}");

            return response()->json([
                'success' => true,
                'message' => __('Factories synced successfully.'),
                'redirect_url' => route('admin.catalog.product.design-template', [
                    'product' => $product->id,
                ]),
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
