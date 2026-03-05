<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\Enums\Shipping\ShippingProviderEnum;
use App\Models\Shipping\ShippingPartner;
use App\Services\Shipping\Providers\AfterShipService;
use App\Services\Shipping\Providers\ShipStationService;
use Exception;

class ShippingProviderManager
{
    /**
     * Resolve the appropriate shipping provider based on the partner setting.
     *
     * @throws Exception
     */
    public static function resolve(ShippingPartner $partner): ShippingProviderInterface
    {
        $providerCode = $partner->code;
        $providerEnum = ShippingProviderEnum::tryFrom($providerCode);

        return match ($providerEnum) {
            ShippingProviderEnum::AfterShip => app()->makeWith(AfterShipService::class, ['partner' => $partner]),
            ShippingProviderEnum::ShipStation => app()->makeWith(ShipStationService::class, ['partner' => $partner]),
            default => throw new Exception("Unsupported shipping provider: {$providerCode}"),
        };
    }
}
