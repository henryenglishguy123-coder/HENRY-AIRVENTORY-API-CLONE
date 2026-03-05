<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreChannelSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('store_channels')->insert([
            [
                'code' => 'shopify',
                'name' => 'Shopify',
                'logo' => 'store-logos/shopify.svg',
                'description' => 'Leading global eCommerce platform for online stores and retail systems',
                'auth_type' => 'oauth',
                'required_credentials' => json_encode([
                    'client_id' => true,
                    'client_secret' => true,
                    'scopes' => [
                        'read_products',
                        'write_products',
                        'read_orders',
                    ],
                ]),
                'is_active' => true,
            ],
            [
                'code' => 'woocommerce',
                'name' => 'WooCommerce',
                'logo' => 'store-logos/woocommerce.svg',
                'description' => 'A customizable, open-source eCommerce solution',
                'auth_type' => 'api_key',
                'required_credentials' => json_encode([
                    'store_url' => true,
                    'consumer_key' => true,
                    'consumer_secret' => true,
                ]),
                'is_active' => true,
            ],
        ]);
    }
}
