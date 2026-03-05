<?php

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'external_product_id' => $this->resource['external_product_id'] ?? null,
            'title' => $this->resource['title'] ?? null,
            'description' => $this->resource['description'] ?? null,
            'primary_image' => $this->resource['primary_image'] ?? null,
            'options' => $this->resource['options'] ?? [],
            'variants' => $this->resource['variants'] ?? [],
        ];
    }
}
