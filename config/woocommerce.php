<?php

return [
    'sync' => [
        'variation_batch_size' => (int) env('WOO_VARIATION_BATCH_SIZE', 50),
        'max_concurrent_batches' => (int) env('WOO_MAX_CONCURRENT_BATCHES', 5),
    ],
];
