<?php

namespace App\Models\Customer\Store;

use App\Enums\Store\StoreConnectionStatus;
use App\Models\Customer\Vendor;
use App\Models\StoreChannels\StoreChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorConnectedStore extends Model
{
    use HasFactory;

    protected $table = 'vendor_connected_stores';

    protected $fillable = [
        'vendor_id',
        'channel',
        'link',
        'token',
        'store_identifier',
        'currency',
        'additional_data',
        'status',
        'last_synced_at',
        'last_order_sync_at',
        'error_message',
    ];

    protected $hidden = [
        'token',
        'vendor_id',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'last_synced_at' => 'datetime',
        'last_order_sync_at' => 'datetime',
        'status' => StoreConnectionStatus::class,
    ];

    protected $attributes = [
        'status' => StoreConnectionStatus::CONNECTED,
    ];

    protected $appends = [
        'is_connected',
        'status_label',
    ];

    /**
     * Resolve store ID from numeric ID or string identifier.
     *
     * @param  int|string  $identifier
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function resolveId($identifier): int
    {
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        // Handle string identifiers
        $id = static::where('store_identifier', $identifier)->value('id');

        if (! $id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Store not found for identifier: {$identifier}");
        }

        return $id;
    }

    /* ==============================
     | Relationships
     | ============================== */

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /* ==============================
     | Scopes
     | ============================== */

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    public function scopeDisconnected($query)
    {
        return $query->where('status', 'disconnected');
    }

    public function scopeErrored($query)
    {
        return $query->where('status', 'error');
    }

    /* ==============================
     | Accessors
     | ============================== */

    public function getIsConnectedAttribute(): bool
    {
        return $this->status === StoreConnectionStatus::CONNECTED;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            StoreConnectionStatus::CONNECTED => __('Connected'),
            StoreConnectionStatus::DISCONNECTED => __('Disconnected'),
            StoreConnectionStatus::ERROR => __('Error'),
            default => __('Unknown'),
        };
    }

    /* ==============================
     | Helpers
     | ============================== */

    public function markConnected(): void
    {
        $this->update([
            'status' => StoreConnectionStatus::CONNECTED,
            'error_message' => null,
            'last_synced_at' => now(),
        ]);
    }

    public function markDisconnected(?string $reason = null): void
    {
        $this->update([
            'status' => StoreConnectionStatus::DISCONNECTED,
            'error_message' => $reason,
        ]);
    }

    public function markError(string $message): void
    {
        $this->update([
            'status' => StoreConnectionStatus::ERROR,
            'error_message' => $message,
        ]);
    }

    public function storeChannel()
    {
        return $this->belongsTo(StoreChannel::class, 'channel', 'code');
    }
}
