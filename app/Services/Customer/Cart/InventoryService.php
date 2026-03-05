<?php

namespace App\Services\Customer\Cart;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplate;
use Illuminate\Support\Collection;

class InventoryService
{
    /**
     * Find a factory with available stock for the given variant
     */
    public function findFactoryWithStock(
        CatalogProduct $variant,
        ?VendorDesignTemplate $template = null
    ): ?int {
        $factoriesWithStock = $variant->inventories()
            ->where('stock_status', 1)
            ->where(function ($q) {
                $q->where('manage_inventory', 0)
                    ->orWhere(function ($q) {
                        $q->where('manage_inventory', 1)
                            ->where('quantity', '>', 0);
                    });
            })
            ->pluck('factory_id')
            ->filter() // avoid null factory_id
            ->values()
            ->toArray();

        if (empty($factoriesWithStock)) {
            return null;
        }

        // Prefer template factory if valid
        if (
            $template &&
            $template->factory_id &&
            in_array($template->factory_id, $factoriesWithStock, true)
        ) {
            return $template->factory_id;
        }

        return $factoriesWithStock[0];
    }

    /**
     * Check if a specific factory has stock for a variant
     */
    public function hasStockInFactory(
        CatalogProduct $variant,
        int $factoryId
    ): bool {
        return $variant->inventories()
            ->where('factory_id', $factoryId)
            ->where('stock_status', 1)
            ->where(function ($q) {
                $q->where('manage_inventory', 0)
                    ->orWhere(function ($q) {
                        $q->where('manage_inventory', 1)
                            ->where('quantity', '>', 0);
                    });
            })
            ->exists();
    }

    /**
     * Get all factories with their stock information for a product
     */
    public function getFactoryStockInfo(
        CatalogProduct $product
    ): Collection {
        return $product->inventories()
            ->with('factory')
            ->get()
            ->map(function ($inventory) {
                $inStock = $inventory->stock_status == 1
                    && (
                        $inventory->manage_inventory == 0
                        || ($inventory->manage_inventory == 1 && $inventory->quantity > 0)
                    );

                return [
                    'factory_id' => $inventory->factory_id,
                    'factory_name' => $inventory->factory->name ?? 'Unknown',
                    'quantity' => $inventory->manage_inventory ? $inventory->quantity : null,
                    'manage_inventory' => (bool) $inventory->manage_inventory,
                    'stock_status' => $inventory->stock_status == 1
                        ? __('In Stock')
                        : __('Out of Stock'),
                    'in_stock' => $inStock,
                ];
            });
    }
}
