<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_shipment_number' => $this->sales_shipment_number,
            'tracking_name' => $this->tracking_name,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'label_url' => $this->label_url,
            'waybill_number' => $this->waybill_number,
            'status' => $this->status,
            'provider_name' => $this->tracking_name,
            'created_at' => $this->created_at,
            'tracking_logs' => ShipmentTrackingLogResource::collection($this->whenLoaded('trackingLogs')),
        ];
    }
}
