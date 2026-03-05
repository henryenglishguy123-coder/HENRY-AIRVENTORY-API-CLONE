<?php

namespace App\Http\Controllers\Api\V1\Catalog\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InventoryController extends Controller
{
    private function resolveFactoryId(Request $request): ?int
    {
        if (Auth::guard('admin_api')->check()) {
            return $request->input('factory_id') ?? $request->query('factory_id');
        }
        if (Auth::guard('factory')->check()) {
            return Auth::guard('factory')->id();
        }

        return null;
    }

    private function buildQuery(int $factoryId, Request $request)
    {
        $qParam = trim((string) $request->get('q', ''));
        $qParam = addcslashes($qParam, '%_\\');
        $stock = $request->get('stock_status');
        $manage = $request->get('manage_inventory');
        $skuExact = $request->get('sku_exact');
        $optionKey = $request->get('option');
        $optionKey = $optionKey ? addcslashes((string) $optionKey, '%_\\') : '';
        $saleOnly = $request->boolean('sale_only');
        $hasFactoryPrice = $request->boolean('has_factory_price');
        $inStock = $request->has('in_stock') ? $request->boolean('in_stock') : null;
        $query = CatalogProduct::query()
            ->where(function ($wq) use ($factoryId) {
                $wq->whereExists(
                    DB::table('catalog_product_prices')
                        ->selectRaw('1')
                        ->whereColumn('catalog_product_prices.catalog_product_id', 'catalog_products.id')
                        ->where('factory_id', $factoryId)
                )->orWhereExists(
                    DB::table('catalog_product_inventory')
                        ->selectRaw('1')
                        ->whereColumn('catalog_product_inventory.product_id', 'catalog_products.id')
                        ->where('factory_id', $factoryId)
                );
            })
            ->with([
                'info:id,catalog_product_id,name',
                'parent.info:id,catalog_product_id,name',
                'files:catalog_product_id,image,order',
                'parent.files:catalog_product_id,image,order',
                'prices' => function ($q) use ($factoryId) {
                    $q->whereNull('factory_id')
                        ->orWhere('factory_id', $factoryId);
                },
                'inventories' => function ($q) use ($factoryId) {
                    $q->where('factory_id', $factoryId);
                },
                'parent.inventory:id,product_id,manage_inventory,quantity,stock_status',
                'inventory:id,product_id,manage_inventory,quantity,stock_status',
                'attributes.option',
            ]);
        if ($qParam !== '') {
            $query->where(function ($qq) use ($qParam) {
                $qq->where('sku', 'like', "%{$qParam}%")
                    ->orWhereHas('info', function ($q1) use ($qParam) {
                        $q1->where('name', 'like', "%{$qParam}%");
                    })
                    ->orWhereHas('parent.info', function ($q2) use ($qParam) {
                        $q2->where('name', 'like', "%{$qParam}%");
                    });
            });
        }
        if (! is_null($manage)) {
            $val = is_string($manage) ? strtolower($manage) : $manage;
            if (in_array($val, ['yes', '1', 1, true], true)) {
                $query->whereHas('parent.inventory', function ($iq) {
                    $iq->where('manage_inventory', 1);
                });
            } elseif (in_array($val, ['no', '0', 0, false], true)) {
                $query->whereHas('parent.inventory', function ($iq) {
                    $iq->where('manage_inventory', 0);
                });
            }
        }
        if ($skuExact) {
            $query->where('sku', $skuExact);
        }
        if ($optionKey) {
            $ok = trim((string) $optionKey);
            $query->where(function ($qq) use ($ok) {
                $qq->whereHas('attributes.option', function ($oq) use ($ok) {
                    $oq->where('key', 'like', "%{$ok}%");
                })->orWhereHas('attributes', function ($aq) use ($ok) {
                    $aq->where('attribute_value', 'like', "%{$ok}%");
                });
            });
        }
        if ($saleOnly) {
            $query->where(function ($qq) use ($factoryId) {
                $qq->whereHas('prices', function ($pq) use ($factoryId) {
                    $pq->where('factory_id', $factoryId)->whereNotNull('sale_price')->where('sale_price', '>', 0);
                })->orWhereHas('prices', function ($pq) {
                    $pq->whereNull('factory_id')->whereNotNull('sale_price')->where('sale_price', '>', 0);
                });
            });
        }
        if ($hasFactoryPrice) {
            $query->whereHas('prices', function ($pq) use ($factoryId) {
                $pq->where('factory_id', $factoryId);
            });
        }
        if (! is_null($stock)) {
            $query->where(function ($qq) use ($factoryId, $stock) {
                $qq->whereHas('inventories', function ($iq) use ($factoryId, $stock) {
                    $iq->where('factory_id', $factoryId)->where('stock_status', (int) $stock);
                })->orWhereHas('inventory', function ($iq) use ($stock) {
                    $iq->where('stock_status', (int) $stock);
                });
            });
        } elseif (! is_null($inStock)) {
            $want = $inStock ? 1 : 0;
            $query->where(function ($qq) use ($factoryId, $want) {
                $qq->whereHas('inventories', function ($iq) use ($factoryId, $want) {
                    $iq->where('factory_id', $factoryId)->where('stock_status', $want);
                })->orWhereHas('inventory', function ($iq) use ($want) {
                    $iq->where('stock_status', $want);
                });
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $factoryId = $this->resolveFactoryId($request);
        if (! $factoryId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Factory context required',
            ], 400);
        }

        // Filter variants by factory-linked price or inventory rows via DB-side exists clauses

        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $sortBy = (string) $request->get('sort_by', 'id');
        $sortDir = strtolower((string) $request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = $this->buildQuery($factoryId, $request);
        if (in_array($sortBy, ['id', 'sku'], true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (CatalogProduct $variant) use ($factoryId) {
            $parent = $variant->parent;
            $parentImage = optional(optional($parent)->files->first())->url ?? null;
            $variantImage = optional($variant->files->first())->url ?? null;
            $image = $parentImage ?? $variantImage;

            $basePrice = $variant->prices->whereNull('factory_id')->first();
            $factoryPrice = $variant->prices->firstWhere('factory_id', $factoryId);
            $factoryInv = $variant->inventories->firstWhere('factory_id', $factoryId);
            $parentInv = optional($parent)->inventory;
            $manageInventory = (bool) optional($parentInv)->manage_inventory;

            $calcRegular = optional($basePrice)->regular_price;
            $calcSale = optional($basePrice)->sale_price;

            $regular = optional($factoryPrice)->regular_price ?? $calcRegular;
            $sale = optional($factoryPrice)->sale_price ?? $calcSale;

            $stockStatus = optional($factoryInv)->stock_status ?? optional($variant->inventory)->stock_status ?? 1;
            $qty = $manageInventory ? (optional($factoryInv)->quantity ?? optional($variant->inventory)->quantity) : null;
            $options = $variant->attributes->map(function ($attribute) {
                return optional($attribute->option)->key ?? $attribute->attribute_value;
            })->filter()->implode(' / ');

            return [
                'id' => $variant->id,
                'name' => optional($variant->info)->name,
                'sku' => $variant->sku,
                'image' => $image,
                'manage_inventory' => $manageInventory,
                'quantity' => $qty,
                'stock_status' => $stockStatus,
                'regular_price' => $regular,
                'sale_price' => $sale,
                'options' => $options,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
                'total_pages' => $paginator->lastPage(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'filters' => [
                    'q' => $request->get('q'),
                    'stock_status' => $request->get('stock_status'),
                    'manage_inventory' => $request->get('manage_inventory'),
                    'sku_exact' => $request->get('sku_exact'),
                    'parent_id' => $request->get('parent_id'),
                    'option' => $request->get('option'),
                    'sale_only' => $request->boolean('sale_only'),
                    'has_factory_price' => $request->boolean('has_factory_price'),
                    'in_stock' => $request->has('in_stock') ? $request->boolean('in_stock') : null,
                ],
            ],
            'message' => 'Factory products retrieved successfully.',
        ], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $factoryId = $this->resolveFactoryId($request);
        if (! $factoryId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Factory context required',
            ], 400);
        }

        $payload = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required_without:items.*.sku', 'integer', 'exists:catalog_products,id'],
            'items.*.sku' => ['required_without:items.*.id', 'string', 'filled', 'max:100', 'exists:catalog_products,sku'],
            'items.*.regular_price' => ['nullable', 'numeric'],
            'items.*.sale_price' => ['nullable', 'numeric'],
            'items.*.quantity' => ['nullable', 'integer'],
            'items.*.stock_status' => ['nullable'],
        ]);

        DB::beginTransaction();
        try {
            $updated = 0;
            foreach ($payload['items'] as $item) {
                $variant = null;
                if (isset($item['id'])) {
                    $variant = CatalogProduct::find((int) $item['id']);
                } elseif (array_key_exists('sku', $item)) {
                    $sku = trim((string) $item['sku']);
                    if ($sku !== '') {
                        $variant = CatalogProduct::query()->where('sku', $sku)->first();
                    }
                }
                if (! $variant) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Invalid item identifier: provide a valid id or non-empty sku',
                    ], 422);
                }
                $parent = $variant->parent;
                $manageInventory = (bool) optional(optional($parent)->inventory)->manage_inventory;

                $priceUpdate = [];
                if (array_key_exists('regular_price', $item)) {
                    $priceUpdate['regular_price'] = $item['regular_price'];
                }
                if (array_key_exists('sale_price', $item)) {
                    $priceUpdate['sale_price'] = $item['sale_price'];
                }
                if (! empty($priceUpdate)) {
                    CatalogProductPrice::updateOrCreate(
                        [
                            'catalog_product_id' => $variant->id,
                            'factory_id' => $factoryId,
                        ],
                        $priceUpdate
                    );
                }

                if (array_key_exists('quantity', $item) || array_key_exists('stock_status', $item)) {
                    $invPayload = [
                        'manage_inventory' => $manageInventory ? 1 : 0,
                    ];
                    if ($manageInventory && array_key_exists('quantity', $item)) {
                        $invPayload['quantity'] = $item['quantity'] ?? null;
                    }
                    if (array_key_exists('stock_status', $item) && ! is_null($item['stock_status'])) {
                        $raw = $item['stock_status'];
                        if (is_string($raw)) {
                            $val = strtolower(trim($raw));
                            if (in_array($val, ['in', 'instock'], true)) {
                                $invPayload['stock_status'] = 1;
                            } elseif (in_array($val, ['out', 'outofstock'], true)) {
                                $invPayload['stock_status'] = 0;
                            } else {
                                $invPayload['stock_status'] = (int) $val;
                            }
                        } else {
                            $invPayload['stock_status'] = ((int) $raw) === 1 ? 1 : 0;
                        }
                    }
                    CatalogProductInventory::updateOrCreate(
                        [
                            'product_id' => $variant->id,
                            'factory_id' => $factoryId,
                        ],
                        $invPayload
                    );
                }
                $updated++;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factory products updated successfully.',
                'data' => [
                    'updated' => $updated,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'An internal error occurred while updating products.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $factoryId = $this->resolveFactoryId($request);
        if (! $factoryId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Factory context required',
            ], 400);
        }

        $query = $this->buildQuery($factoryId, $request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="factory_products_'.$factoryId.'.csv"',
        ];

        return response()->streamDownload(function () use ($query, $factoryId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['variant_id', 'sku', 'name', 'parent_name', 'quantity', 'stock_status', 'regular_price', 'sale_price']);
            foreach ($query->cursor() as $variant) {
                $factoryPrice = $variant->prices->firstWhere('factory_id', $factoryId);
                $basePrice = $variant->prices->firstWhere('factory_id', null);
                $effectivePrice = $factoryPrice ?: $basePrice;

                $factoryInv = $variant->inventories->firstWhere('factory_id', $factoryId);
                $baseInv = $variant->inventory;
                $effectiveInv = $factoryInv ?: $baseInv;

                fputcsv($out, [
                    $variant->id,
                    $variant->sku,
                    optional($variant->info)->name,
                    optional(optional($variant->parent)->info)->name,
                    optional($effectiveInv)->quantity,
                    optional($effectiveInv)->stock_status,
                    optional($effectivePrice)->regular_price,
                    optional($effectivePrice)->sale_price,
                ]);
            }
            fclose($out);
        }, 'factory_products_'.$factoryId.'.csv', $headers);
    }

    public function import(Request $request)
    {
        $factoryId = $this->resolveFactoryId($request);
        if (! $factoryId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Factory context required',
            ], 400);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'mimetypes:text/plain,text/csv,application/csv', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $sfo = new \SplFileObject($path);
        $sfo->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        DB::beginTransaction();
        try {
            $header = null;
            $processed = 0;
            $skippedRows = [];
            $lineNo = 0;
            foreach ($sfo as $row) {
                $lineNo++;
                if (! $row || count($row) === 1 && $row[0] === null) {
                    continue;
                }
                if ($header === null) {
                    $header = array_map(function ($h) {
                        return Str::of((string) $h)->trim()->lower()->toString();
                    }, $row);

                    continue;
                }
                $data = [];
                foreach ($header as $i => $key) {
                    $data[$key] = $row[$i] ?? null;
                }

                $variant = null;
                if (! empty($data['variant_id'])) {
                    $variant = CatalogProduct::find($data['variant_id']);
                }
                if (! $variant && ! empty($data['sku'])) {
                    $variant = CatalogProduct::query()->where('sku', $data['sku'])->first();
                }
                if (! $variant) {
                    $skippedRows[] = [
                        'row' => $lineNo,
                        'raw' => $data,
                        'reason' => 'no variant match by variant_id or sku',
                    ];

                    continue;
                }
                $parent = $variant->parent;
                $manageInventory = (bool) optional(optional($parent)->inventory)->manage_inventory;

                $priceUpdate = [];
                if (array_key_exists('regular_price', $data) && is_numeric($data['regular_price'] ?? null)) {
                    $priceUpdate['regular_price'] = (float) $data['regular_price'];
                }
                if (array_key_exists('sale_price', $data) && is_numeric($data['sale_price'] ?? null)) {
                    $priceUpdate['sale_price'] = (float) $data['sale_price'];
                }
                if (! empty($priceUpdate)) {
                    CatalogProductPrice::updateOrCreate(
                        [
                            'catalog_product_id' => $variant->id,
                            'factory_id' => $factoryId,
                        ],
                        $priceUpdate
                    );
                }

                $invPayload = [
                    'manage_inventory' => $manageInventory ? 1 : 0,
                ];
                if ($manageInventory && array_key_exists('quantity', $data)) {
                    $invPayload['quantity'] = is_numeric($data['quantity'] ?? null) ? (int) $data['quantity'] : null;
                }
                if (array_key_exists('stock_status', $data) && ($data['stock_status'] ?? '') !== '') {
                    $raw = $data['stock_status'];
                    if (is_string($raw)) {
                        $val = strtolower(trim($raw));
                        if (in_array($val, ['in', 'instock'], true)) {
                            $invPayload['stock_status'] = 1;
                        } elseif (in_array($val, ['out', 'outofstock'], true)) {
                            $invPayload['stock_status'] = 0;
                        } else {
                            $invPayload['stock_status'] = (int) $val;
                        }
                    } else {
                        $invPayload['stock_status'] = ((int) $raw) === 1 ? 1 : 0;
                    }
                }
                if ($manageInventory || array_key_exists('quantity', $data) || array_key_exists('stock_status', $data)) {
                    CatalogProductInventory::updateOrCreate(
                        [
                            'product_id' => $variant->id,
                            'factory_id' => $factoryId,
                        ],
                        $invPayload
                    );
                }
                $processed++;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factory products imported successfully.',
                'data' => [
                    'processed' => $processed,
                    'skipped_rows' => $skippedRows,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'An internal error occurred while importing products.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
