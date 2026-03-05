<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Country extends Model
{
    use HasFactory;

    public const CACHE_KEY_ALL = 'public_countries_list_all';

    public const CACHE_KEY_ALLOWED = 'public_countries_list_allowed';

    protected $casts = [
        'timezones' => 'array',
    ];

    public $timestamps = false;

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'numeric_code',
        'phonecode',
        'currency',
        'currency_name',
        'currency_symbol',
        'timezones',
        'emoji',
        'is_allowed',
        'is_default',
        'is_state_available',
        'is_zipcode_available',
    ];

    public function scopeAllowed($query)
    {
        return $query->where('is_allowed', 1);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }

    public function factoryBusinesses()
    {
        return $this->hasMany(\App\Models\Factory\FactoryBusiness::class, 'country_id');
    }

    public static function getAllowedCountries()
    {
        return self::allowed()->get();
    }

    public static function getDefaultCountry()
    {
        return self::default()->first();
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    /**
     * Clear public countries cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
        Cache::forget(self::CACHE_KEY_ALLOWED);
    }
}
