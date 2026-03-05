<?php

namespace App\Support\Customers;

use App\Models\Customer\VendorMeta;

class CustomerMeta
{
    /**
     * Get meta value for a customer (vendor).
     */
    public static function get(int|string $vendorId, string $key, $default = null)
    {
        $meta = VendorMeta::query()
            ->where('vendor_id', $vendorId)
            ->where('key', $key)
            ->first();

        return $meta->value ?? $default;
    }

    /**
     * Alias for update() to support existing tests.
     */
    public static function set(int|string $vendorId, string $key, $value): void
    {
        self::update($vendorId, $key, $value);
    }

    /**
     * Insert or update meta value.
     */
    public static function update(int|string $vendorId, string $key, $value): void
    {
        VendorMeta::updateOrCreate(
            [
                'vendor_id' => $vendorId,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Delete meta by key for a customer (vendor).
     */
    public static function delete(int|string $vendorId, string $key): void
    {
        VendorMeta::where('vendor_id', $vendorId)
            ->where('key', $key)
            ->delete();
    }
}
