<?php

namespace App\Services\Customer\Payments\Contracts;

interface GatewayInterface
{
    public function createSetupIntent(int $vendorId);

    public function saveMethod(array $data);

    public function listMethods(int $vendorId);

    public function deleteMethod(int $vendorId, int $methodId);

    public function chargeSavedMethod(int $vendorId, string $savedMethodId, float $amount);
}
