<?php

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
{
    /**
     * Transform the search results into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        $results = $this->resource;

        return [
            'orders' => $this->formatResults($results['orders'] ?? null, 'order'),
            'templates' => $this->formatResults($results['templates'] ?? null, 'template'),
            'stores' => $this->formatResults($results['stores'] ?? null, 'store'),
            'catalog' => $this->formatResults($results['catalog'] ?? null, 'catalog'),
        ];
    }

    /**
     * Format results for a specific type
     */
    protected function formatResults(?array $data, string $type): array
    {
        if (! $data) {
            return [
                'total' => 0,
                'items' => [],
                'pagination' => [
                    'total' => 0,
                    'count' => 0,
                    'per_page' => 0,
                    'current_page' => 1,
                    'total_pages' => 0,
                ],
                'hasMore' => false,
            ];
        }

        return [
            'total' => $data['total'] ?? 0,
            'items' => $this->formatItems($data['items'] ?? [], $type),
            'pagination' => [
                'total' => $data['total'] ?? 0,
                'count' => $data['count'] ?? ($data['items'] ?? collect())->count(),
                'per_page' => $data['per_page'] ?? 0,
                'current_page' => $data['page'] ?? 1,
                'total_pages' => $data['total_pages'] ?? 0,
            ],
            'hasMore' => $data['hasMore'] ?? false,
        ];
    }

    /**
     * Format items based on type
     */
    protected function formatItems($items, string $type): array
    {
        if (empty($items)) {
            return [];
        }

        return collect($items)->map(function ($item) use ($type) {
            return match ($type) {
                'order' => $this->formatOrder($item),
                'template' => $this->formatTemplate($item),
                'store' => $this->formatStore($item), // Redundant but kept for safety if type mismatch
                'catalog' => $this->formatCatalog($item),
                default => [],
            };
        })->toArray();
    }

    /**
     * Format order item
     */
    protected function formatOrder($order): array
    {
        return [
            'id' => $order->id,
            'type' => 'order',
            'order_number' => $order->order_number,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'total' => [
                'raw' => $order->grand_total_inc_margin,
                'formatted' => format_price($order->grand_total_inc_margin),
            ],
            'created_at' => $order->created_at?->toIso8601String(),
            'source' => $order->sourceInfo ? [
                'platform' => $order->sourceInfo->platform,
                'name' => $order->sourceInfo->source,
            ] : null,
            'customer_name' => $order->shippingAddress
                ? trim($order->shippingAddress->first_name.' '.$order->shippingAddress->last_name)
                : null,
        ];
    }

    /**
     * Format template item
     */
    protected function formatTemplate($template): array
    {
        return [
            'id' => $template->id,
            'type' => 'template',
            'title' => $template->information?->name ?? 'Untitled Template',
            'product_name' => $template->product?->info?->name ?? null,
            'product_slug' => $template->product?->slug ?? null,
            'stores' => ($template->storeOverrides ?? collect())->map(fn ($override) => [
                'id' => $override->connectedStore?->id,
                'name' => $override->connectedStore?->store_identifier,
            ])->toArray(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format store item
     */
    protected function formatStore($store): array
    {
        return (new ConnectedStoreResource($store))->resolve();
    }

    /**
     * Format catalog item
     */
    protected function formatCatalog($product): array
    {
        return [
            'id' => $product->id,
            'type' => 'catalog',
            'name' => $product->name, // mapped from select alias in service
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category' => $this->getCategoryName($product),
            'image' => $product->files->first()?->url,
        ];
    }

    protected function getCategoryName($product): ?array
    {
        $category = $product->categories->first();
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->meta?->name ?? $category->slug,
            'slug' => $category->slug,
        ];
    }
}
