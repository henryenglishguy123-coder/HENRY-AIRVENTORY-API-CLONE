<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this;

        $isFactory = $request->user('factory') !== null;

        return [
            'name' => $item->product_name,
            'sku' => $item->sku,
            'options' => $item->relationLoaded('options') ? OrderItemOptionResource::collection($item->options) : null,
            'designs' => $item->relationLoaded('designs') ? OrderItemDesignResource::collection($item->designs) : null,
            'price' => format_price($isFactory ? $item->row_price : $item->row_price_inc_margin),
            'quantity' => $item->qty,
            'subtotal' => format_price($isFactory ? $item->subtotal : $item->subtotal_inc_margin),
            'branding' => $item->relationLoaded('branding') ? [
                'packaging_label' => $item->branding?->appliedPackagingLabel?->name,
                'hang_tag' => $item->branding?->appliedHangTag?->name,
            ] : null,
        ];
    }
}
