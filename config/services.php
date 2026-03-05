<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'fixer' => [
        'base_url' => env('FIXER_API_BASE_URL', 'https://data.fixer.io/api/latest'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'shopify' => [
        'key' => env('SHOPIFY_API_KEY'),
        'secret' => env('SHOPIFY_API_SECRET'),
        'scopes' => env('SHOPIFY_API_SCOPES'),
        'api_version' => env('SHOPIFY_API_VERSION'),
        'timeout' => env('SHOPIFY_API_TIMEOUT', 30),
    ],
    'woocommerce' => [
        'webhook_secret' => env('WOOCOMMERCE_WEBHOOK_SECRET'),
    ],

    'shipstation' => [
        'api_key' => env('SHIPSTATION_API_KEY'),
        'base_url' => env('SHIPSTATION_BASE_URL', 'https://ssapi.shipstation.com'),
        'carrier_id' => env('SHIPSTATION_CARRIER_ID', 'stamps_com'),
        'service_code' => env('SHIPSTATION_SERVICE_CODE', 'usps_priority_mail'),
    ],

    'aftership' => [

        'api_key' => env('AFTERSHIP_API_KEY'),
        'base_url' => env('AFTERSHIP_BASE_URL', 'https://api.aftership.com'),
        'api_version' => 'v4',
        'postmen_version' => 'postmen/v3',

        'tracking_url' => 'https://track.aftership.com/',

        'yunexpress' => [
            'default_service_type' => 'yunexpress_domestic_express',
            'paper_size' => 'default',
            'requires_customs' => true,

            'service_types' => [
                'yunexpress_america_direct_line_standard_non_battery',
                'yunexpress_dhl_express',
                'yunexpress_direct_line_for_apprael_tracked',
                'yunexpress_direct_line_track',
                'yunexpress_direct_line_tracked_au_post',
                'yunexpress_direct_line_tracked_dg_goods',
                'yunexpress_domestic_express',
                'yunexpress_epacket_sz_branch',
                'yunexpress_global_direct_line_standard_battery',
                'yunexpress_global_direct_line_standard_track',
                'yunexpress_global_direct_line_tracked_battery',
                'yunexpress_global_direct_line_tracked_non_battery',
                'yunexpress_jp_direct_line_track',
                'yunexpress_middle_east_direct_line_ddp',
                'yunexpress_middle_east_direct_line_track',
                'yunexpress_us_direct_line_tracked_remote_area',
                'yunexpress_zj_direct_line_track',
            ],
        ],
    ],
];
