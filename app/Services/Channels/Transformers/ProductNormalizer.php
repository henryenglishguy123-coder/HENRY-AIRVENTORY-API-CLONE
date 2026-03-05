<?php

namespace App\Services\Channels\Transformers;

class ProductNormalizer
{
    public static function shopify(array $data): array
    {
        $optionNames = array_map(fn ($o) => $o['name'] ?? null, $data['options'] ?? []);
        $primaryImage = $data['image'] ?? (($data['images'] ?? [])[0] ?? null);
        $variants = [];
        foreach (($data['variants'] ?? []) as $v) {
            $values = [];
            foreach ($optionNames as $i => $name) {
                if ($name) {
                    $key = 'option'.($i + 1);
                    $values[$name] = $v[$key] ?? null;
                }
            }
            $variants[] = [
                'id' => $v['id'] ?? null,
                'title' => $v['title'] ?? null,
                'sku' => $v['sku'] ?? null,
                'options' => $values,
            ];
        }

        return [
            'external_product_id' => $data['id'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['body_html'] ?? null,
            'primary_image' => $primaryImage ? [
                'id' => $primaryImage['id'] ?? null,
                'src' => $primaryImage['src'] ?? null,
            ] : null,
            'options' => array_map(function ($o) {
                return [
                    'name' => $o['name'] ?? null,
                    'values' => $o['values'] ?? [],
                ];
            }, $data['options'] ?? []),
            'variants' => $variants,
        ];
    }

    public static function woocommerce(array $data, ?array $wooVariations = null): array
    {
        $primaryImage = ($data['images'] ?? [])[0] ?? null;
        $options = array_map(function ($attr) {
            return [
                'name' => $attr['name'] ?? null,
                'values' => $attr['options'] ?? [],
            ];
        }, $data['attributes'] ?? []);
        $variants = [];
        foreach (($wooVariations ?? []) as $v) {
            $optMap = [];
            foreach (($v['attributes'] ?? []) as $att) {
                $n = $att['name'] ?? null;
                if ($n) {
                    $optMap[$n] = $att['option'] ?? null;
                }
            }
            $variants[] = [
                'id' => $v['id'] ?? null,
                'title' => $data['name'] ?? null,
                'sku' => $v['sku'] ?? null,
                'options' => $optMap,
            ];
        }

        return [
            'external_product_id' => $data['id'] ?? null,
            'title' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'primary_image' => $primaryImage ? [
                'id' => $primaryImage['id'] ?? null,
                'src' => $primaryImage['src'] ?? null,
            ] : null,
            'options' => $options,
            'variants' => $variants,
        ];
    }
}
