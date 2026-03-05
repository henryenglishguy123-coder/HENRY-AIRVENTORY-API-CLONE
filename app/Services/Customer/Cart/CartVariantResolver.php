<?php

namespace App\Services\Customer\Cart;

use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Support\Collection;

class CartVariantResolver
{
    public function resolve(CatalogProduct $parent, Collection $selectedOptions): ?CatalogProduct
    {
        $normalizedSelectedOptions = $selectedOptions
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        return $parent->children()
            ->whereHas('attributes', function ($q) use ($normalizedSelectedOptions) {
                $q->whereIn('attribute_value', $normalizedSelectedOptions);
            }, '=', $normalizedSelectedOptions->count())
            ->with('attributes')
            ->get()
            ->first(function ($child) use ($normalizedSelectedOptions) {
                $childOptions = $child->attributes
                    ->pluck('attribute_value')
                    ->map(fn ($value) => (int) $value)
                    ->sort()
                    ->values();

                return $childOptions->count() === $normalizedSelectedOptions->count()
                    && $childOptions->all() === $normalizedSelectedOptions->all();
            });
    }
}
