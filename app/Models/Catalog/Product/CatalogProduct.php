<?php

namespace App\Models\Catalog\Product;

use App\Models\Catalog\Category\CatalogCategory;
use App\Models\Factory\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'slug',
        'sku',
        'status',
        'weight',
    ];

    public function childrenMappings()
    {
        return $this->hasMany(CatalogProductParent::class, 'parent_id');
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogProduct::class,
            'catalog_product_parents',
            'parent_id',
            'catalog_product_id'
        );
    }

    public function scopeParents($query)
    {
        return $query->whereHas('childrenMappings');
    }

    /**
     * Get the single parent of this variant.
     */
    public function parent()
    {
        return $this->hasOneThrough(
            CatalogProduct::class,        // The model we want to access (The Parent)
            CatalogProductParent::class,  // The intermediate pivot model
            'catalog_product_id',         // Foreign key on pivot table pointing to the Child (this product)
            'id',                         // Foreign key on target table (Parent's ID)
            'id',                         // Local key on source table (Child's ID)
            'parent_id'                   // Local key on pivot table pointing to the Parent
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeCategory($query, $category)
    {
        return $query->whereHas('categories', function ($q) use ($category) {
            if (is_numeric($category)) {
                $q->where('catalog_categories.id', (int) $category);
            } else {
                $q->where('catalog_categories.slug', $category);
            }
        });
    }

    public function scopeBrand($query, $brand)
    {
        return $query->whereHas('attributes', function ($q) use ($brand) {
            $q->whereHas('attribute', function ($subQ) {
                $subQ->where('attribute_code', 'brand');
            })
                ->whereHas('option', function ($subQ) use ($brand) {
                    if (is_numeric($brand)) {
                        $subQ->where('option_id', (int) $brand);
                    } else {
                        $subQ->where('option_value', $brand);
                    }
                });
        });
    }

    public function scopePriceBetween($query, ?float $min, ?float $max)
    {
        if ($min === null && $max === null) {
            return $query;
        }

        return $query->whereHas('pricesWithMargin', function ($q) use ($min, $max) {
            $q->where(function ($p) use ($min, $max) {
                $p->whereNotNull('sale_price')
                    ->when($min !== null, fn ($pp) => $pp->where('sale_price', '>=', $min))
                    ->when($max !== null, fn ($pp) => $pp->where('sale_price', '<=', $max));
            })->orWhere(function ($p) use ($min, $max) {
                $p->whereNotNull('regular_price')
                    ->when($min !== null, fn ($pp) => $pp->where('regular_price', '>=', $min))
                    ->when($max !== null, fn ($pp) => $pp->where('regular_price', '<=', $max));
            });
        });
    }

    public function scopeAvailable($query, ?bool $inStock)
    {
        if ($inStock === null) {
            return $query;
        }

        return $query->whereHas('inventories', function ($q) use ($inStock) {
            $q->when($inStock === true, function ($qq) {
                $qq->where('stock_status', 1)
                    ->where(function ($inner) {
                        $inner->where('manage_inventory', 0)
                            ->orWhere(function ($inner2) {
                                $inner2->where('manage_inventory', 1)
                                    ->where('quantity', '>', 0);
                            });
                    });
            })
                ->when($inStock === false, function ($qq) {
                    $qq->where(function ($inner) {
                        $inner->where('stock_status', 0)
                            ->orWhere(function ($inner2) {
                                $inner2->where('manage_inventory', 1)
                                    ->where('quantity', '<=', 0);
                            });
                    });
                });
        });
    }

    public function info()
    {
        return $this->hasOne(CatalogProductInfo::class, 'catalog_product_id');
    }

    public function files()
    {
        return $this->hasMany(CatalogProductFile::class, 'catalog_product_id')->orderBy('order');
    }

    public function inventory()
    {
        return $this->hasOne(CatalogProductInventory::class, 'product_id');
    }

    public function inventories()
    {
        return $this->hasMany(CatalogProductInventory::class, 'product_id');
    }

    public function factories(): HasManyThrough
    {
        return $this->hasManyThrough(
            Factory::class,
            CatalogProductInventory::class,
            'product_id', // Foreign key on catalog_product_inventory table
            'id', // Foreign key on factories table
            'id', // Local key on catalog_products table
            'factory_id' // Local key on catalog_product_inventory table
        );
    }

    public function factoriesWithStock()
    {
        return $this->inventories()
            ->with('factory')
            ->where('stock_status', 1)
            ->where(function ($q) {
                $q->where('manage_inventory', 0)
                    ->orWhere(function ($q) {
                        $q->where('manage_inventory', 1)
                            ->where('quantity', '>', 0);
                    });
            })
            ->get()
            ->map(fn ($inventory) => $inventory->factory)
            ->filter();
    }

    /**
     * Get all factories with their inventory information for this product
     */
    /**
     * Get all factories with their inventory information for this product
     */
    public function factoryInventory()
    {
        return $this->inventories()
            ->with('factory')
            ->get()
            ->mapWithKeys(function ($inventory) {

                $inStock = (int) $inventory->stock_status === 1
                    && (
                        (int) $inventory->manage_inventory === 0
                        || (
                            (int) $inventory->manage_inventory === 1
                            && $inventory->quantity > 0
                        )
                    );

                return [
                    $inventory->factory_id => [
                        'factory' => $inventory->factory,
                        'quantity' => (int) $inventory->manage_inventory === 1
                            ? $inventory->quantity
                            : null,
                        'manage_inventory' => (bool) $inventory->manage_inventory,
                        'stock_status' => (int) $inventory->stock_status === 1
                            ? __('In Stock')
                            : __('Out of Stock'),
                        'in_stock' => $inStock,
                    ],
                ];
            });
    }

    public function prices(): HasMany
    {
        return $this->hasMany(
            CatalogProductPrice::class,
            'catalog_product_id'
        );
    }

    public function pricesWithMargin(): HasMany
    {
        return $this->hasMany(
            CatalogProductPriceWithMargin::class,
            'catalog_product_id'
        );
    }

    public function activePrices(): HasMany
    {
        return $this->prices()->whereNotNull('factory_id');
    }

    public function attributes()
    {
        return $this->hasMany(CatalogProductAttribute::class, 'catalog_product_id');
    }

    public function categories()
    {
        return $this->belongsToMany(
            CatalogCategory::class,
            CatalogProductCategory::class,
            'catalog_product_id',
            'catalog_category_id'
        );
    }

    public function designTemplate()
    {
        return $this->hasOne(CatalogProductDesignTemplate::class, 'catalog_product_id');
    }

    public function printingPrices()
    {
        return $this->hasMany(
            CatalogProductPrintingPrice::class,
            'catalog_product_id'
        );
    }

    public function layerImages()
    {
        return $this->hasMany(
            CatalogProductLayerImage::class,
            'catalog_product_id'
        );
    }

    public function getTemplateIntegrityStatus(): array
    {
        $template = $this->relationLoaded('designTemplate')
            ? $this->designTemplate?->catalogDesignTemplate
            : $this->designTemplate()->first()?->catalogDesignTemplate;
        $isActive = $template && (int) $template->status === 1;
        $printingPrices = $this->relationLoaded('printingPrices')
            ? $this->printingPrices
            : $this->printingPrices()->with('layer')->get();
        $hasPrices = $printingPrices->isNotEmpty();
        $layersComplete = $hasPrices && $printingPrices->every(
            fn ($pp) => $pp->layer && ! empty($pp->layer->image)
        );
        $isValid = $isActive && $layersComplete;

        return [
            'is_valid' => $isValid,
            'name' => $template?->name ?? __('Not Assigned'),
            'reason' => ! $isActive
                ? __('Inactive or Missing Template')
                : (! $layersComplete ? __('Missing Layer Images') : __('Ready')),
        ];
    }

    public function getPriceRangeWithMargin(): array
    {
        $children = $this->children()
            ->with('pricesWithMargin')
            ->get();
        $prices = $children
            ->flatMap(fn ($child) => $child->pricesWithMargin)
            ->map(function ($price) {
                return ($price->sale_price && $price->sale_price > 0)
                    ? $price->sale_price
                    : $price->regular_price;
            })
            ->filter()
            ->values();
        if ($prices->isEmpty()) {
            return [
                'min' => null,
                'max' => null,
                'range' => null,
            ];
        }
        $min = (float) $prices->min();
        $max = (float) $prices->max();

        return [
            'min' => $min,
            'max' => $max,
            'range' => $min === $max
                ? format_price($min)
                : format_price($min).' - '.format_price($max),
        ];
    }
}
