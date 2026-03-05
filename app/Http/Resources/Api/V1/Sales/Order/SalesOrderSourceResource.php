<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use App\Models\Admin\Store\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderSourceResource extends JsonResource
{
    protected static ?Store $cachedStore = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $platform = $this->platform ?? 'airventory';

        return [
            'platform' => $platform,
            'source' => $this->source,
            'source_order_id' => $this->source_order_id,
            'source_order_number' => $this->source_order_number,
            'source_created_at' => $this->source_created_at,
            'logo_url' => $this->getLogoUrl($platform),
        ];
    }

    protected function getLogoUrl(string $platform): string
    {
        // 1. Use preloaded channel if available
        if ($this->resource->relationLoaded('channel') && $this->channel) {
            if ($this->channel->logo_url) {
                return $this->channel->logo_url;
            }
        }
        // If not loaded or no logo, try Store fallback if platform is not airventory?
        // Or just proceed to resolveStoreLogoUrl with default store?
        // The original code tried to find channel by code.
        // If we strictly follow "refactor so the resource does not query the DB", we assume eager loading.
        // If channel is not loaded, we shouldn't query.

        return self::resolveStoreLogoUrl(self::getDefaultStore());
    }

    protected static function getDefaultStore(): ?Store
    {
        if (self::$cachedStore === null) {
            self::$cachedStore = Store::orderBy('id')->first();
        }

        return self::$cachedStore;
    }

    protected static function resolveStoreLogoUrl(?Store $store): string
    {
        if ($store && $store->favicon) {
            return getImageUrl($store->favicon);
        }

        return asset('assets/images/logo-mini.svg');
    }

    public static function default(): array
    {
        return [
            'platform' => 'airventory',
            'source' => __('Airventory Order'),
            'source_order_id' => null,
            'source_order_number' => null,
            'source_created_at' => null,
            'logo_url' => self::resolveStoreLogoUrl(self::getDefaultStore()),
        ];
    }
}
