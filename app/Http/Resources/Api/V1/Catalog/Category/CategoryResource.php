<?php

namespace App\Http\Resources\Api\V1\Catalog\Category;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $hasMeta = $this->relationLoaded('meta');
        $meta = $hasMeta ? $this->meta : null;

        return [
            'id' => $this->id,
            'name' => $meta?->name ?? null,
            'image' => [
                'original' => $hasMeta ? getImageUrl($meta->image) : null,
                'thumbnail' => $hasMeta
                    ? getImageUrl($meta->image, true, ['width' => 150, 'height' => 150])
                    : null,
            ],

            'slug' => $this->slug,
            'path' => $this->path,
            'slug_path' => $this->slug_path,
            'children' => CategoryResource::collection(
                $this->whenLoaded('children')
            ),
        ];
    }
}
