<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentTrackingLogResource extends JsonResource
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
            'status' => $this->status,
            'sub_status' => $this->sub_status,
            'description' => $this->description,
            'location' => $this->location,
            'checkpoint_time' => $this->checkpoint_time?->toIso8601String(),
            'provider' => $this->provider,
        ];
    }
}
