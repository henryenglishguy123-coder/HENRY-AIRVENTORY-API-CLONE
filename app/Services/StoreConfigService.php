<?php

namespace App\Services;

use App\Models\Admin\Store\Store;
use App\Models\Admin\Store\StoreMeta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StoreConfigService
{
    public const CONFIG_CACHE_KEY = 'store.config.all';

    public function getConfig(): Collection
    {
        return Cache::rememberForever(self::CONFIG_CACHE_KEY, function () {
            $store = Store::query()->first();
            if (! $store) {
                return collect([]);
            }
            $meta = StoreMeta::query()->where('store_id', $store->id)->pluck('value', 'key');

            return collect($store->attributesToArray())->merge($meta);
        });
    }

    public function get(string $key, $default = null): mixed
    {
        return $this->getConfig()->get($key, $default);
    }

    public function clearCache(): void
    {
        Cache::forget(self::CONFIG_CACHE_KEY);
        Store::clearPanelCache();
        StoreMeta::clearPanelCache();
    }
}
