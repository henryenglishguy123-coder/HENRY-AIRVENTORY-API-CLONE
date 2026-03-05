<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemDesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'layer_name' => $this->layer_name,
            'preview_image' => $this->preview_image ? getImageUrl($this->preview_image) : null,
        ];
    }
}
