<?php

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectedStoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $link = $this->link;
        $normalizedLink = preg_replace('#^https?://#', '', $link);

        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'channel_logo' => $this->storeChannel?->logo_url,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_connected' => $this->is_connected,
            'link' => str_starts_with($link, 'http') ? $link : "https://{$link}",
            'domain' => $normalizedLink,
            'store_identifier' => $this->store_identifier,
            'last_synced_at' => $this->last_synced_at,
            'error_message' => $this->error_message,
            'connected_at' => $this->created_at,
        ];
    }
}
