<?php

namespace App\Models\Admin\Store;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StoreMeta extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'panel.config.store_meta';

    protected $fillable = ['store_id', 'key', 'value', 'type'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saved(fn () => self::clearPanelCache());
        static::deleted(fn () => self::clearPanelCache());
    }

    public static function clearPanelCache(): void
    {
        Cache::forget(Store::PANEL_CONFIG_CACHE_KEY);
        Cache::forget(self::CACHE_KEY);
    }
}
