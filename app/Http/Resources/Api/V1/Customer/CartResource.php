<?php

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        $total = $this->totals;

        return [
            'id' => $this->id,

            'items' => collect($this->items)->map(function ($item) {

                /* =========================
                 | Options (safe)
                 ========================= */
                $options = collect($item->options);

                $colorOption = $options->first(
                    fn ($opt) => ($opt->option_code ?? null) === 'color'
                );

                /* =========================
                 | Design images (safe)
                 ========================= */
                $designImages = collect($item->designImages);
                $designImage = null;

                if ($colorOption && ! empty($colorOption->option_id)) {
                    $designImage = $designImages
                        ->firstWhere('color_id', $colorOption->option_id);
                }

                if (! $designImage) {
                    $designImage = $designImages->first();
                }

                /* =========================
                 | Variations (safe)
                 ========================= */
                $variations = [];
                if ($item->template) {
                    $item->template->loadMissing([
                        'designImages',
                        'product.children.attributes.option.attribute',
                        'product.info',
                    ]);

                    $variations = $this->buildVariations($item->template);
                }

                return [
                    'id' => $item->id,
                    'template_id' => $item->template_id,
                    'variations' => $variations,

                    'product_title' => $item->product_title,
                    'product_id' => $item->product->id,
                    'sku' => $item->sku,
                    'qty' => (int) ($item->qty ?? 0),

                    'unit_price' => format_price($item->unit_price ?? 0),
                    'line_total' => format_price($item->line_total ?? 0),

                    'selected_options' => $options
                        ->map(function ($option) {
                            return collect($option)
                                ->except([
                                    'id',
                                    'cart_item_id',
                                    'created_at',
                                    'updated_at',
                                ])
                                ->toArray();
                        })
                        ->values()
                        ->toArray(),

                    'temp_image' => $designImage
                        ? getImageUrl($designImage->image ?? null)
                        : null,
                    'fulfillment_factory_id' => $item->fulfillment_factory_id,
                ];
            })->values()->toArray(),

            'shipments' => collect($this->items)
                ->groupBy('fulfillment_factory_id')
                ->map(function ($items, $factoryId) {
                    return [
                        'factory_id' => $factoryId ?: null,
                        'items' => $items->pluck('id')->toArray(),
                    ];
                })
                ->values()
                ->toArray(),

            /* =========================
             | Totals (safe)
             ========================= */
            'totals' => [
                'subtotal' => format_price($total?->subtotal ?? 0),
                'discount_total' => format_price($total?->discount_total ?? 0),
                'subtotal_tax' => format_price($total?->subtotal_tax ?? 0),
                'shipping_amount' => format_price($total?->shipping_amount ?? 0),
                'shipping_tax' => format_price($total?->shipping_tax ?? 0),
                'shipping_total' => format_price($total?->shipping_total ?? 0),
                'tax_total' => format_price($total?->tax_total ?? 0),
                'grand_total' => format_price($total?->grand_total ?? 0),
            ],

            /* =========================
             | Discount (safe)
             ========================= */
            'discount' => $this->discount ? [
                'code' => $this->discount->code,
                'amount' => format_price($this->discount->amount),
            ] : null,

            'errors' => $this->errors ?? [],

            /* =========================
             | Address (safe)
             ========================= */
            'address' => collect($this->address)
                ->except(['id', 'cart_id', 'created_at', 'updated_at'])
                ->toArray(),
        ];
    }

    protected function buildVariations($template): array
    {
        $children = $template->product?->children;

        if (! $children || $children->isEmpty()) {
            return [];
        }

        $attributes = [];

        foreach ($children as $child) {
            foreach ($child->attributes ?? [] as $attributeValue) {

                $attribute = $attributeValue->attribute ?? null;
                $option = $attributeValue->option ?? null;

                if (! $attribute || ! $option) {
                    continue;
                }

                $code = $attribute->attribute_code ?? null;
                $optionId = $option->option_id ?? null;

                if (! $code || ! $optionId || isset($attributes[$code][$optionId])) {
                    continue;
                }

                $data = [
                    'id' => $optionId,
                    'key' => $option->key ?? null,
                    'value' => $option->option_value ?? null,
                ];

                if ($code === 'color') {
                    $data['image'] = getImageUrl(
                        $template->designImages
                            ?->firstWhere('color_id', $optionId)
                            ?->image
                    );
                }

                $attributes[$code][$optionId] = $data;
            }
        }

        return collect($attributes)
            ->map(fn ($options) => array_values($options))
            ->toArray();
    }
}
