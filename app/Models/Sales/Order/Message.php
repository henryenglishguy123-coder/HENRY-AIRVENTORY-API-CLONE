<?php

namespace App\Models\Sales\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $table = 'sales_order_messages';

    protected $fillable = [
        'sales_order_id',
        'sender_id',
        'sender_role',
        'message',
        'attachments',
        'message_type',
    ];

    protected $appends = ['sender_name'];

    protected $casts = [
        'attachments' => 'array', // Cast to array for JSON handling
        'message_type' => 'string',
        'sender_role' => 'string',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sales\Order\SalesOrder::class, 'sales_order_id');
    }

    public function sender(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('sender', 'sender_role', 'sender_id');
    }

    /**
     * Helper to format a sender's name.
     */
    private function formatSenderName($sender, string $fallback): string
    {
        $name = trim(($sender->first_name ?? '').' '.($sender->last_name ?? ''));

        return ! empty($name) ? $name : $fallback;
    }

    /**
     * Get the descriptive name of the sender based on their role and model data.
     */
    public function getSenderNameAttribute(): string
    {
        // Optimization: if we're inside a loop and haven't eager loaded sender,
        // this will still cause N+1. But we add a check for robustness.
        $sender = $this->sender;
        if (! $sender) {
            return 'Deleted User';
        }

        switch ($this->sender_role) {
            case 'admin':
                return $sender->name ?? 'Admin';
            case 'factory':
                // Use company name if available (requires 'business' relationship)
                if ($sender->relationLoaded('business') && $sender->business?->company_name) {
                    return $sender->business->company_name;
                }

                return $this->formatSenderName($sender, 'Factory User');
            case 'customer':
                // Customers are Vendors
                return $this->formatSenderName($sender, 'Customer');
            default:
                return 'Unknown';
        }
    }

    // Scopes
    public function scopeByOrder($query, $orderNumber)
    {
        return $query->whereHas('order', function ($q) use ($orderNumber) {
            $q->where('order_number', $orderNumber);
        });
    }

    public function scopeBySenderRole($query, $role)
    {
        return $query->where('sender_role', $role);
    }

    public function scopeByMessageType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    public function scopeWithAttachments($query)
    {
        return $query->whereJsonLength('attachments', '>', 0);
    }
}
