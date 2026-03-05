<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Deduplication TTL (hours)
    |--------------------------------------------------------------------------
    |
    | The number of hours to cache email deduplication keys. This prevents
    | the same email from being queued multiple times within the TTL window.
    |
    */

    'email_dedupe_hours' => env('NOTIFICATIONS_EMAIL_DEDUPE_HOURS', 24),

];
