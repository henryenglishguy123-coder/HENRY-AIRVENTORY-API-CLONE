<?php

return [
    'date_format' => 'M jS, Y',
    'datetime_format' => 'M jS, Y h:i a',
    'time_format' => 'h:i a',
    'customer_login_url' => env('CUSTOMER_PANEL_URL', env('APP_URL', 'http://localhost').'/customer/login'),
];
