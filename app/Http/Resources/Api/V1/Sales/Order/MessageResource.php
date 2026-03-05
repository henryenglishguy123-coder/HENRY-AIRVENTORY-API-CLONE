<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'sales_order_id' => $this->sales_order_id,
            'message' => $this->message,
            'attachments' => is_array($this->attachments) && count($this->attachments) > 0 ? collect($this->attachments)->map(function ($attachment) {
                return [
                    'url' => $attachment['url'] ?? null,
                    'name' => $attachment['name'] ?? null,
                    'extension' => $attachment['extension'] ?? null,
                    'mime_type' => $attachment['mime_type'] ?? null,
                ];
            })->values()->toArray() : null,
            'message_type' => $this->message_type,
            'sender_role' => $this->sender_role,
            'sender_name' => $this->sender_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
