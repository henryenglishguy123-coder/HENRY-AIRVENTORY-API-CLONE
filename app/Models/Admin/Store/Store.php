<?php

namespace App\Models\Admin\Store;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    public const PANEL_CONFIG_CACHE_KEY = 'panel.config.store';

    public const PANEL_TIMEZONE_KEY = 'panel.config.store.timezone';

    protected $casts = [
        'social_links' => 'json',
    ];

    protected $fillable = [
        'id',
        'store_name',
        'email',
        'mobile',
        'meta_title',
        'meta_description',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'zip',
        'embedded_map_code',
        'social_links',
        'timezone',
        'icon',
        'favicon',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::clearPanelCache());
        static::deleted(fn () => self::clearPanelCache());
        static::forceDeleted(fn () => self::clearPanelCache());
    }

    public static function clearPanelCache(): void
    {
        Cache::forget(self::PANEL_CONFIG_CACHE_KEY);
        Cache::forget(self::PANEL_TIMEZONE_KEY);
    }

    public function meta(): HasMany
    {
        return $this->hasMany(StoreMeta::class, 'store_id');
    }
}
