<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VendorNotification extends Model
{
    use HasFactory;

    protected $table = 'vendor_notifications';

    // Mass assignable fields
    protected $fillable = [
        'vendor_id',
        'type',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // Scope for unread notifications
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // Mark notification as read
    public function markAsRead(): self
    {
        $this->update(['read_at' => Carbon::now()]);

        return $this;
    }

    // Accessor for checking if read
    public function getIsReadAttribute(): bool
    {
        return ! is_null($this->read_at);
    }

    // Optional: Accessor for data safely
    public function getDataAttribute($value): array
    {
        return json_decode($value, true) ?? [];
    }

    // Optional: Mutator for data
    public function setDataAttribute($value): void
    {
        $this->attributes['data'] = json_encode($value);
    }
}
