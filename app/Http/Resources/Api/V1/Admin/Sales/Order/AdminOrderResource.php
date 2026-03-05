<?php

namespace App\Http\Resources\Api\V1\Admin\Sales\Order;

use App\Http\Resources\Api\V1\Sales\Order\OrderAddressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
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
            'created_at' => $this->created_at->format(config('admin.datetime_format')),
            'billing_address' => new OrderAddressResource($this->whenLoaded('billingAddress')),
            'shipping_address' => new OrderAddressResource($this->whenLoaded('shippingAddress')),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'email' => $this->customer->email,
            ] : null,
            'factory' => $this->factory ? [
                'id' => $this->factory->id,
                'name' => $this->factory->business && $this->factory->business->company_name
                    ? $this->factory->business->company_name
                    : trim($this->factory->first_name.' '.$this->factory->last_name),
            ] : null,
        ];
    }
}
