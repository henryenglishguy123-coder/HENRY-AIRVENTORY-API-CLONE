<?php

namespace App\Services\Store;

use App\Enums\Store\StoreConnectionStatus;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Support\Facades\DB;

class StoreConnectionService
{
    public function connect(array $data, string $channel): VendorConnectedStore
    {
        $requiredKeys = ['vendor_id', 'store_identifier', 'link', 'token'];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Missing required field: {$key}");
            }
        }

        return DB::transaction(function () use ($data, $channel) {
            return VendorConnectedStore::updateOrCreate(
                [
                    'vendor_id' => $data['vendor_id'],
                    'channel' => $channel,
                    'store_identifier' => $data['store_identifier'],
                ],
                [
                    'link' => $data['link'],
                    'token' => $data['token'],
                    'currency' => $data['currency'] ?? null,
                    'additional_data' => $data['additional_data'] ?? null,
                    'status' => StoreConnectionStatus::CONNECTED,
                    'last_synced_at' => now(),
                    'error_message' => null,
                ]
            );
        });
    }
}
