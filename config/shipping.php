<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shipping Module Configuration
    |--------------------------------------------------------------------------
    |
    | Centralised configuration for all shipping-related defaults.
    | Provider-specific credentials live in config/services.php.
    |
    */

    /*
    | Default package dimensions used when no dimensions are available from
    | the order (e.g. when product weight/dimensions are not catalogued).
    | These are in inches — adjust per your typical shipment size.
    */
    'default_dimensions' => [
        'length' => (int) env('SHIPPING_DEFAULT_LENGTH', 5),
        'width' => (int) env('SHIPPING_DEFAULT_WIDTH', 10),
        'height' => (int) env('SHIPPING_DEFAULT_HEIGHT', 10),
        'depth' => (int) env('SHIPPING_DEFAULT_DEPTH', 10),
        'unit' => env('SHIPPING_DEFAULT_DIM_UNIT', 'cm'),
    ],

    /*
    | Default country of origin (ISO 2-letter code) used for customs
    | declarations when the factory business does not have a country set.
    */
    'default_origin_country' => env('SHIPPING_DEFAULT_ORIGIN_COUNTRY', 'US'),

    /*
    | Minimum package weight used when calculating shipping costs
    | and package dimensions
    */
    'minimum_package_weight' => env('SHIPPING_MINIMUM_PACKAGE_WEIGHT', 0.1),

    /*
    | Weight unit for shipping calculations
    */
    'weight_unit' => env('SHIPPING_WEIGHT_UNIT', 'kg'),

    /*
    | Customs configuration options
    */
    'customs' => [
        'purpose_options' => [
            env('SHIPPING_CUSTOMS_PURPOSE_1', 'merchandise'),
            env('SHIPPING_CUSTOMS_PURPOSE_2', 'gift'),
            env('SHIPPING_CUSTOMS_PURPOSE_3', 'document'),
            env('SHIPPING_CUSTOMS_PURPOSE_4', 'return_merchandise'),
            env('SHIPPING_CUSTOMS_PURPOSE_5', 'sample'),
        ],
        'terms_of_trade_options' => [
            env('SHIPPING_TERMS_OF_TRADE_1', 'ddu'),
            env('SHIPPING_TERMS_OF_TRADE_2', 'ddp'),
            env('SHIPPING_TERMS_OF_TRADE_3', 'cpt'),
            env('SHIPPING_TERMS_OF_TRADE_4', 'cip'),
        ],
        'purpose' => env('SHIPPING_CUSTOMS_PURPOSE', 'merchandise'),
        'terms_of_trade' => env('SHIPPING_CUSTOMS_TERMS_OF_TRADE', 'ddu'),
        'billing_paid_by' => env('SHIPPING_CUSTOMS_BILLING_PAID_BY', 'recipient'),
        'commercial_invoice_title' => env('SHIPPING_COMMERCIAL_INVOICE_TITLE', 'Commercial Invoice'),
        'date_format' => env('SHIPPING_DATE_FORMAT', 'Y-m-d'),
    ],

    /*
    | Webhook secrets used to verify inbound tracking webhooks from providers.
    | Each provider has its own HMAC secret. Leave null to bypass verification
    | (not recommended in production).
    |
    | Key pattern: shipping.webhooks.{provider_code}.secret
    */
    'webhooks' => [
        'shipstation' => [
            'secret' => env('SHIPSTATION_WEBHOOK_SECRET', null),
        ],
        'aftership' => [
            'secret' => env('AFTERSHIP_WEBHOOK_SECRET', null),
        ],
    ],

];
