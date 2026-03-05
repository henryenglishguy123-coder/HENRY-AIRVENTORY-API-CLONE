<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingPartnerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shipping_partners')->upsert([
            [
                'code' => 'shipstation',
                'name' => 'ShipStation',
                'logo' => null,
                'type' => 'shipping',
                'api_base_url' => 'https://ssapi.shipstation.com',
                'app_id' => null,
                'api_key' => null,
                'api_secret' => null,
                'webhook_secret' => null,
                'is_enabled' => true,
                'last_sync_status' => null,
                'last_sync_at' => null,
            ],
            [
                'code' => 'aftership',
                'name' => 'AfterShip',
                'logo' => null,
                'type' => 'tracking',
                'api_base_url' => 'https://api.aftership.com/v4',
                'app_id' => null,
                'api_key' => null,
                'api_secret' => null,
                'webhook_secret' => null,
                'is_enabled' => true,
                'last_sync_status' => null,
                'last_sync_at' => null,
            ],
        ], ['code'], [
            'name',
            'logo',
            'type',
            'api_base_url',
            'app_id',
            'api_key',
            'api_secret',
            'webhook_secret',
            'is_enabled',
            'last_sync_status',
            'last_sync_at',
        ]);
    }
}
