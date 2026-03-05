<?php

namespace App\Services\Channels\Normalization;

use Symfony\Component\HttpFoundation\Response;

class StoreUrlNormalizer
{
    public function normalize(string $channel, string $input): string
    {
        return match ($channel) {
            'woocommerce' => $this->normalizeWoo($input),
            'shopify' => $this->normalizeShopify($input),
            default => abort(Response::HTTP_BAD_REQUEST),
        };
    }

    protected function normalizeWoo(string $url): string
    {
        return rtrim($url, '/');
    }

    protected function normalizeShopify(string $shop): string
    {
        $shop = trim($shop);
        $shop = preg_replace('#^https?://#i', '', $shop);
        $shop = rtrim($shop, '/');
        if (! str_ends_with($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        return 'https://'.$shop;
    }
}
