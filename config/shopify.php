<?php

return [
    'sync' => [
        'variation_batch_size' => (int) env('SHOPIFY_VARIATION_BATCH_SIZE', 50),
        'max_concurrent_batches' => (int) env('SHOPIFY_MAX_CONCURRENT_BATCHES', 5),
    ],
];
