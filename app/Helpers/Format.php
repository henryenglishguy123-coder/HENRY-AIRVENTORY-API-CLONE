<?php

use App\Models\Admin\Store\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

if (! function_exists('formatDateTime')) {
    function formatDateTime($datetime, $zone = null, $format = null, $fallback = null)
    {
        $storeTimezone = Cache::rememberForever(
            Store::PANEL_TIMEZONE_KEY,
            fn () => Store::value('timezone') ?: config('app.timezone')
        );

        if (empty($datetime)) {
            return $fallback ?? __('N/A');
        }

        $zone = $zone ?? $storeTimezone;
        $format = $format ?? config('admin.datetime_format', 'Y-m-d H:i:s');

        try {
            return Carbon::parse($datetime)
                ->timezone($zone)
                ->format($format);
        } catch (\Throwable $e) {
            return $fallback ?? __('Invalid date');
        }
    }
}
