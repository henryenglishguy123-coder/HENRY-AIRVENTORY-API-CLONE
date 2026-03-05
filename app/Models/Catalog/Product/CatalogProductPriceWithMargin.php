<?php

namespace App\Models\Catalog\Product;

use App\Services\StoreConfigService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class CatalogProductPriceWithMargin extends Model
{
    use HasFactory;

    protected $table = 'catalog_product_prices';

    protected $fillable = [
        'catalog_product_id',
        'store_id',
        'factory_id',
        'regular_price',
        'sale_price',
        'specific_markup',
    ];

    protected $casts = [
        'regular_price' => 'float',
        'sale_price' => 'float',
        'specific_markup' => 'float',
    ];

    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | MARKUP RESOLVING
    |--------------------------------------------------------------------------
    */

    public static function getGlobalMarkup(): float
    {
        return Cache::remember('global_markup', 86400, function () {
            $markup = app(StoreConfigService::class)->get('profit_global_markup');

            return is_numeric($markup) ? (float) $markup : 0.0;
        });
    }

    protected function resolveMarkup(): float
    {
        return (! is_null($this->specific_markup) && is_numeric($this->specific_markup))
            ? floatval($this->specific_markup)
            : self::getGlobalMarkup();
    }

    public function getApplicableMarkupPercentage(): float
    {
        return $this->resolveMarkup();
    }

    protected function apply(float $basePrice): float
    {
        $margin = max(0, (float) $this->resolveMarkup());
        $marginDecimal = $margin / 100;
        if ($marginDecimal >= 1) {
            return $basePrice;
        }

        return $basePrice / (1 - $marginDecimal);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS (GETTERS)
    |--------------------------------------------------------------------------
    */

    protected function regularPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->attributes['regular_price'])
            ? null
            : $this->apply($this->attributes['regular_price']),
        );
    }

    protected function salePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->attributes['sale_price'])
            ? null
            : $this->apply($this->attributes['sale_price']),
        );
    }

    protected function baseRegularPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['regular_price'] ?? null,
        );
    }

    protected function baseSalePrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['sale_price'] ?? null,
        );
    }

    public function getEffectiveBasePrice(): ?float
    {
        return $this->baseSalePrice ?? $this->baseRegularPrice ?? null;
    }

    public function getEffectivePriceAttribute(): ?float
    {
        $base = $this->getEffectiveBasePrice();

        return ! is_null($base) ? $this->apply($base) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Factory\Factory::class, 'factory_id');
    }
}
