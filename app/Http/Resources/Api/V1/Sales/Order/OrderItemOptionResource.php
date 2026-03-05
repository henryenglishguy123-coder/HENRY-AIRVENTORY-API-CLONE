<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->option_name,
            'value' => $this->option_value,
        ];
    }
}
