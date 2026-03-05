<?php

namespace App\Services\Channels\Validation;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreUrlValidator
{
    public function validate(string $channel, Request $request): void
    {
        $rules = match ($channel) {
            'woocommerce' => $this->wooRules(),
            'shopify' => $this->shopifyRules(),
            default => abort(Response::HTTP_BAD_REQUEST, __('Unsupported store channel')),
        };

        $request->validate($rules);
    }

    protected function wooRules(): array
    {
        return [
            'store_url' => [
                'required',
                'url',
                'max:255',
                function ($attribute, $value, $fail) {
                    $host = parse_url($value, PHP_URL_HOST);

                    if (! $host) {
                        $fail(__('Invalid store URL.'));

                        return;
                    }

                    if (filter_var($host, FILTER_VALIDATE_IP)) {
                        if (! filter_var(
                            $host,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                        )) {
                            $fail(__('The store URL cannot point to internal networks.'));
                        }
                    }
                },
            ],
        ];
    }

    protected function shopifyRules(): array
    {
        return [
            'store_url' => [
                'required',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9\-]{2,62}(\.myshopify\.com)?$/i',
            ],
        ];
    }
}
