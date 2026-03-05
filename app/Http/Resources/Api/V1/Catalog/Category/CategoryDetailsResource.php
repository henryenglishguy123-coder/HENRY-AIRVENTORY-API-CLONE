<?php

namespace App\Http\Resources\Api\V1\Catalog\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = $this->meta;
        $imagePath = $meta?->image;

        return [
            'slug' => $this->slug,
            'name' => $meta?->name ?? null,
            'description' => $meta?->description ?? null,

            'image' => [
                'original' => $imagePath ? getImageUrl($imagePath) : null,
                'thumbnail' => $imagePath
                    ? getImageUrl($imagePath, true, ['width' => 150, 'height' => 150])
                    : null,
            ],

            'children' => $this->whenLoaded('children', function () {
                return $this->children
                    ->map(function ($child) {
                        $childMeta = $child->meta;
                        $childImagePath = $childMeta?->image;

                        return [
                            'slug' => $child->slug,
                            'name' => $childMeta?->name ?? null,
                            'image' => [
                                'original' => $childImagePath ? getImageUrl($childImagePath) : null,
                                'thumbnail' => $childImagePath
                                    ? getImageUrl($childImagePath, true, ['width' => 150, 'height' => 150])
                                    : null,
                            ],
                        ];
                    })
                    ->values();
            }),
        ];
    }
}
