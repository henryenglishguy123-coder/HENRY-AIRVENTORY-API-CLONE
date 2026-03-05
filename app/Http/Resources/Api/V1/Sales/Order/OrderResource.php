<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'grand_total' => $this->grand_total,
            'grand_total_inc_margin' => $this->grand_total_inc_margin,
            'formatted_grand_total' => format_price($this->grand_total),
            'formatted_grand_total_inc_margin' => format_price($this->grand_total_inc_margin),
            'created_at' => $this->created_at,
            'billing_address' => new OrderAddressResource($this->whenLoaded('billingAddress')),
            'shipping_address' => new OrderAddressResource($this->whenLoaded('shippingAddress')),
            'items' => $this->items, // Assuming items are loaded or we want simple array
        ];
    }
}
