<?php

namespace App\Models\Currency;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    use HasFactory;

    /** Cache keys */
    public const CACHE_KEY_DEFAULT = 'default_currency_object';

    public const CACHE_KEY_ALLOWED = 'allowed_currencies_collection';

    protected static ?Currency $cachedDefaultCurrency = null;

    protected $fillable = [
        'currency',
        'code',
        'symbol',
        'localization_code',
        'rate',
        'is_allowed',
        'is_default',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_allowed' => 'boolean',
        'is_default' => 'boolean',
    ];

    /*
    |
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    */

    /**
     * Scope: only default currency.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: only allowed currencies.
     */
    public function scopeAllowed(Builder $query): Builder
    {
        return $query->where('is_allowed', true);
    }

    /*
    |
    |--------------------------------------------------------------------------
    | Static helpers
    |--------------------------------------------------------------------------
    |
    */

    /**
     * Get the default currency.
     *
     * @throws \RuntimeException if no default currency is set.
     */
    public static function getDefaultCurrency(): self
    {
        if (static::$cachedDefaultCurrency !== null) {
            return static::$cachedDefaultCurrency;
        }

        // Try to return cached model if exists and is valid
        $cached = Cache::get(self::CACHE_KEY_DEFAULT);
        if ($cached instanceof self) {
            static::$cachedDefaultCurrency = $cached;

            return $cached;
        }

        $defaultCurrency = static::query()->default()->first();

        if (! $defaultCurrency instanceof self) {
            // Ensure there's nothing stale cached for default
            Cache::forget(self::CACHE_KEY_DEFAULT);
            throw new \RuntimeException(__('Default currency not set.'));
        }

        // Cache only when we have a valid model
        Cache::forever(self::CACHE_KEY_DEFAULT, $defaultCurrency);
        static::$cachedDefaultCurrency = $defaultCurrency;

        return $defaultCurrency;
    }

    /**
     * Same as getDefaultCurrency() but returns null instead of throwing.
     */
    public static function getDefaultCurrencyOrNull(): ?self
    {
        try {
            return static::getDefaultCurrency();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Get all allowed currencies (can be cached if you want).
     */
    public static function getAllowedCurrencies(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY_ALLOWED, function () {
            return static::query()->allowed()->orderBy('code')->get();
        });
    }

    /*
    |
    |--------------------------------------------------------------------------
    | Model events
    |--------------------------------------------------------------------------
    |
    */

    protected static function booted(): void
    {
        $clear = function (): void {
            static::clearCache();
        };

        static::saved($clear);
        static::deleted($clear);
    }

    /**
     * Clear currency-related caches.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_DEFAULT);
        Cache::forget(self::CACHE_KEY_ALLOWED);
        static::$cachedDefaultCurrency = null;
    }

    public static function setDefaultCurrency(self|int $currency): void
    {
        $currencyId = $currency instanceof self ? $currency->getKey() : $currency;

        // Perform updates without opening a new transaction here. Caller should manage transactions.
        static::query()->update(['is_default' => false]);
        static::query()
            ->whereKey($currencyId)
            ->update([
                'is_default' => true,
                'is_allowed' => true,
            ]);

        static::clearCache();
    }

    public static function setAllowedCurrencies(array $currencyIds): void
    {
        $ids = collect($currencyIds)->filter(fn ($id) => ! is_null($id) && $id !== '')->map(fn ($id) => (int) $id)->unique()->values()->all();

        // Perform updates without opening a new transaction here. Caller should manage transactions.
        static::query()->update(['is_allowed' => false]);
        if (! empty($ids)) {
            static::query()->whereIn('id', $ids)->update(['is_allowed' => true]);
        }

        $default = static::getDefaultCurrencyOrNull();
        if ($default && ! in_array($default->id, $ids, true)) {
            static::query()->whereKey($default->id)->update(['is_allowed' => true]);
        }

        static::clearCache();
    }
}
