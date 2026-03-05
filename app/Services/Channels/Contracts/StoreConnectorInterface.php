<?php

namespace App\Services\Channels\Contracts;

use Illuminate\Http\Request;

interface StoreConnectorInterface
{
    public function buildAuthorizeUrl(
        int $vendorId,
        string $storeUrl,
        string $nonce
    ): string;

    public function normalizeInstallPayload(array $payload): array;

    public function validateInstallCallback(Request $request): ?int;

    /**
     * Used for async post-install verification (jobs)
     */
    public function verify(array $credentials): bool;

    /**
     * Sync a vendor design template to the external store.
     *
     * @return string|null The external product ID or null on failure
     */
    public function syncProduct(\App\Models\Customer\Designer\VendorDesignTemplateStore $storeOverride): ?string;

    /**
     * Fetch a product's details from the external store by product ID.
     */
    public function getProductByExternalId(\App\Models\Customer\Store\VendorConnectedStore $store, string $externalId): ?array;
}
