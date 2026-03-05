<?php

namespace App\Http\Resources\Api\V1\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateItemResource extends JsonResource
{
    protected array $variations;

    public function __construct($resource, array $variations = [])
    {
        parent::__construct($resource);
        $this->variations = $variations;
    }

    public function toArray(Request $request): array
    {
        return [
            'template_id' => $this->id,
            'product_id' => $this->product?->id,
            'product_name' => $this->product?->info?->name,
            'variations' => $this->variations,
        ];
    }
}
