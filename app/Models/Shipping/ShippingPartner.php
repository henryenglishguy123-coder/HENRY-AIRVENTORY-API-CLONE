<?php

namespace App\Models\Shipping;

use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingPartner extends Model
{
    protected $table = 'shipping_partners';

    protected $fillable = [
        'name',
        'logo',
        'code',
        'type',
        'api_base_url',
        'app_id',
        'api_key',
        'api_secret',
        'webhook_secret',
        'is_enabled',
        'last_sync_status',
        'last_sync_at',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'settings' => 'array',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(SalesOrderShipment::class, 'shipping_partner_id');
    }

    public function factories()
    {
        return $this->belongsToMany(
            \App\Models\Factory\Factory::class,
            'factory_shipping_partners',
            'shipping_partner_id',
            'factory_id'
        )->withTimestamps()->withPivot('is_active');
    }
}
