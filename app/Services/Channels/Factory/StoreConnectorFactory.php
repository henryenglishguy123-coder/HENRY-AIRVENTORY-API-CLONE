<?php

namespace App\Services\Channels\Factory;

use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Contracts\StoreConnectorInterface;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use InvalidArgumentException;

class StoreConnectorFactory
{
    public function make(StoreChannel $channel): StoreConnectorInterface
    {
        return match ($channel->code) {
            'woocommerce' => app(WooCommerceConnector::class, ['channel' => $channel]),
            'shopify' => app(ShopifyConnector::class, ['channel' => $channel]),
            default => throw new InvalidArgumentException("Unsupported store channel: {$channel->code}"),
        };
    }
}
