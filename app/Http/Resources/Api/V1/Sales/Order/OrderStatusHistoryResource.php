<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
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
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'reason' => $this->reason,
            'source' => $this->source,
            'full_payload' => $this->full_payload,
            'shipping_partner' => $this->shippingPartner ? [
                'id' => $this->shippingPartner->id,
                'name' => $this->shippingPartner->name,
                'code' => $this->shippingPartner->code,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
