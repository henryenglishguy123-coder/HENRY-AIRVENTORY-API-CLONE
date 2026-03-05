<?php

namespace App\Services\Customer;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;

class SearchService
{
    /**
     * Search across all customer resources
     */
    public function search(Vendor $customer, string $query, ?string $type = 'all', int $perPage = 10, int $page = 1, array $filters = []): array
    {
        $results = [];
        $query = trim($query);

        if (empty($query)) {
            return $this->emptyResults();
        }

        // Validate type parameter
        $allowedTypes = ['all', 'orders', 'templates', 'stores', 'catalog'];
        if ($type === null || ! in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        // Pagination validation
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        // Search based on type filter
        if ($type === 'all' || $type === 'orders') {
            $results['orders'] = $this->searchOrders($customer, $query, $perPage, $page, $filters);
        }

        if ($type === 'all' || $type === 'templates') {
            $results['templates'] = $this->searchTemplates($customer, $query, $perPage, $page, $filters);
        }

        if ($type === 'all' || $type === 'stores') {
            $results['stores'] = $this->searchStores($customer, $query, $perPage, $page, $filters);
        }

        if ($type === 'all' || $type === 'catalog') {
            $results['catalog'] = $this->searchCatalog($query, $perPage, $page, $filters);
        }

        return $results;
    }

    /**
     * Search customer orders
     */
    protected function searchOrders(Vendor $customer, string $query, int $perPage, int $page, array $filters): array
    {
        $escapedQuery = $this->escapeLikeWildcards($query);
        $cacheKey = $this->cacheKey($customer->id, 'orders', $query, $page, $perPage, $filters);

        return cache()->remember($cacheKey, now()->addMinutes(1), function () use ($customer, $escapedQuery, $filters, $perPage, $page, $query) {
            $base = $customer->orders()
                ->when(! empty($filters['start_date']) || ! empty($filters['end_date']), function ($q) use ($filters) {
                    $q->dateBetween($filters['start_date'] ?? null, $filters['end_date'] ?? null);
                })
                ->when(! empty($filters['status']), fn ($q) => $q->status($filters['status']))
                ->when(! empty($filters['payment_status']), fn ($q) => $q->paymentStatus($filters['payment_status']))
                ->where(function ($q) use ($escapedQuery) {
                    $q->where('order_number', 'like', "%{$escapedQuery}%")
                        ->orWhereHas('shippingAddress', function ($subQ) use ($escapedQuery) {
                            $subQ->where('first_name', 'like', "%{$escapedQuery}%")
                                ->orWhere('last_name', 'like', "%{$escapedQuery}%")
                                ->orWhere('email', 'like', "%{$escapedQuery}%")
                                ->orWhere('phone', 'like', "%{$escapedQuery}%");
                        })
                        ->orWhereHas('billingAddress', function ($subQ) use ($escapedQuery) {
                            $subQ->where('first_name', 'like', "%{$escapedQuery}%")
                                ->orWhere('last_name', 'like', "%{$escapedQuery}%")
                                ->orWhere('email', 'like', "%{$escapedQuery}%")
                                ->orWhere('phone', 'like', "%{$escapedQuery}%");
                        })
                        ->orWhereHas('sourceInfo', function ($subQ) use ($escapedQuery) {
                            $subQ->where('source', 'like', "%{$escapedQuery}%")
                                ->orWhere('source_order_number', 'like', "%{$escapedQuery}%");
                        });
                });

            $total = (clone $base)->count();

            $items = (clone $base)
                ->select(['id', 'order_number', 'created_at', 'grand_total_inc_margin', 'order_status', 'payment_status'])
                ->with(['sourceInfo:id,order_id,platform,source', 'shippingAddress:id,order_id,first_name,last_name'])
                ->orderBy('created_at', 'desc')
                ->forPage($page, $perPage)
                ->get();

            return $this->buildPaginatedResult($items, $total, $perPage, $page);
        });
    }

    /**
     * Search customer templates
     */
    protected function searchTemplates(Vendor $customer, string $query, int $perPage, int $page, array $filters): array
    {
        $escapedQuery = $this->escapeLikeWildcards($query);
        $cacheKey = $this->cacheKey($customer->id, 'templates', $query, $page, $perPage, $filters);

        return cache()->remember($cacheKey, now()->addMinutes(1), function () use ($customer, $escapedQuery, $filters, $perPage, $page) {
            $base = VendorDesignTemplate::where('vendor_id', $customer->id)
                ->when(! empty($filters['start_date']) || ! empty($filters['end_date']), function ($q) use ($filters) {
                    $q->whereBetween('updated_at', [
                        $filters['start_date'] ?? '1970-01-01',
                        ($filters['end_date'] ?? now()->toDateString()).' 23:59:59',
                    ]);
                })
                ->where(function ($q) use ($escapedQuery) {
                    $q->whereHas('information', function ($subQ) use ($escapedQuery) {
                        $subQ->where('name', 'like', "%{$escapedQuery}%");
                    })
                        ->orWhereHas('product.info', function ($subQ) use ($escapedQuery) {
                            $subQ->where('name', 'like', "%{$escapedQuery}%");
                        })
                        ->orWhereHas('storeOverrides.connectedStore', function ($subQ) use ($escapedQuery) {
                            $subQ->where('store_identifier', 'like', "%{$escapedQuery}%");
                        });
                });

            $total = (clone $base)->count();

            $items = (clone $base)
                ->with([
                    'information:id,vendor_design_template_id,name',
                    'product:catalog_products.id,slug',
                    'product.info:catalog_product_id,name',
                    'storeOverrides.connectedStore:id,store_identifier',
                ])
                ->orderBy('updated_at', 'desc')
                ->forPage($page, $perPage)
                ->get();

            return $this->buildPaginatedResult($items, $total, $perPage, $page);
        });
    }

    /**
     * Search customer connected stores
     */
    protected function searchStores(Vendor $customer, string $query, int $perPage, int $page, array $filters): array
    {
        $escapedQuery = $this->escapeLikeWildcards($query);
        $cacheKey = $this->cacheKey($customer->id, 'stores', $query, $page, $perPage, $filters);

        return cache()->remember($cacheKey, now()->addMinutes(1), function () use ($customer, $escapedQuery, $filters, $perPage, $page) {
            $base = VendorConnectedStore::where('vendor_id', $customer->id)
                ->when(! empty($filters['platform']), fn ($q) => $q->where('channel', $filters['platform']))
                ->where(function ($q) use ($escapedQuery) {
                    $q->where('store_identifier', 'like', "%{$escapedQuery}%")
                        ->orWhere('channel', 'like', "%{$escapedQuery}%");
                });

            $total = (clone $base)->count();

            $items = (clone $base)
                ->select(['id', 'store_identifier', 'channel', 'status', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->with(['storeChannel'])
                ->forPage($page, $perPage)
                ->get();

            return $this->buildPaginatedResult($items, $total, $perPage, $page);
        });
    }

    /**
     * Search catalog products (public, not customer-specific)
     */
    protected function searchCatalog(string $query, int $perPage, int $page, array $filters): array
    {
        $escapedQuery = $this->escapeLikeWildcards($query);
        $cacheKey = $this->cacheKey(0, 'catalog', $query, $page, $perPage, $filters);

        return cache()->remember($cacheKey, now()->addMinutes(1), function () use ($escapedQuery, $filters, $perPage, $page, $query) {
            $base = CatalogProduct::query()
                ->where('catalog_products.status', 'active')
                ->join('catalog_product_infos', 'catalog_products.id', '=', 'catalog_product_infos.catalog_product_id')
                ->when(! empty($filters['category']), fn ($q) => $q->category($filters['category']))
                ->when(array_key_exists('available', $filters), fn ($q) => $q->available($filters['available']))
                ->when(! empty($filters['brand']), fn ($q) => $q->brand($filters['brand']))
                ->when(isset($filters['min_price']) || isset($filters['max_price']), fn ($q) => $q->priceBetween(
                    isset($filters['min_price']) ? (float) $filters['min_price'] : null,
                    isset($filters['max_price']) ? (float) $filters['max_price'] : null
                ))
                ->where(function ($q) use ($escapedQuery) {
                    $q->where('catalog_product_infos.name', 'like', "%{$escapedQuery}%")
                        ->orWhere('sku', 'like', "%{$escapedQuery}%");
                });

            $total = (clone $base)->count();

            $items = (clone $base)
                ->select(['catalog_products.id', 'catalog_product_infos.name as name', 'slug', 'sku'])
                ->orderByRaw(
                    'CASE '.
                    'WHEN sku = ? THEN 200 '.
                    'WHEN sku LIKE ? THEN 150 '.
                    'WHEN catalog_product_infos.name LIKE ? THEN 120 '.
                    'WHEN catalog_product_infos.name LIKE ? THEN 100 '.
                    'ELSE 0 END DESC, catalog_product_infos.name ASC, catalog_products.id ASC',
                    [$query, $escapedQuery.'%', $escapedQuery.'%', '%'.$escapedQuery.'%']
                )
                ->with(['categories.meta', 'files'])
                ->forPage($page, $perPage)
                ->get();

            return $this->buildPaginatedResult($items, $total, $perPage, $page);
        });
    }

    /**
     * Escape LIKE wildcard characters
     */
    protected function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    protected function cacheKey(int $customerId, string $type, string $query, int $page, int $perPage, array $filters): string
    {
        ksort($filters);

        return implode(':', [
            'search',
            $customerId,
            $type,
            md5(mb_strtolower(trim($query))),
            $page,
            $perPage,
            md5(json_encode($filters)),
        ]);
    }

    protected function buildPaginatedResult($items, int $total, int $perPage, int $page): array
    {
        $count = $items->count();
        $totalPages = (int) ceil($total / $perPage);

        return [
            'total' => $total,
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'count' => $count,
            'total_pages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    /**
     * Return empty results structure
     */
    protected function emptyResults(): array
    {
        return [
            'orders' => ['total' => 0, 'items' => [], 'page' => 1, 'per_page' => 10, 'count' => 0, 'total_pages' => 0, 'hasMore' => false],
            'templates' => ['total' => 0, 'items' => [], 'page' => 1, 'per_page' => 10, 'count' => 0, 'total_pages' => 0, 'hasMore' => false],
            'stores' => ['total' => 0, 'items' => [], 'page' => 1, 'per_page' => 10, 'count' => 0, 'total_pages' => 0, 'hasMore' => false],
            'catalog' => ['total' => 0, 'items' => [], 'page' => 1, 'per_page' => 10, 'count' => 0, 'total_pages' => 0, 'hasMore' => false],
        ];
    }
}
