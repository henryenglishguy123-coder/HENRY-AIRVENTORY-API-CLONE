<?php

namespace App\Contracts\Shipping;

use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\DTOs\Shipping\ShipmentResponseDTO;

interface ShippingProviderInterface
{
    /**
     * Create a new shipment for the given sales order.
     */
    public function createShipment(SalesOrder $order, ?string $idempotencyKey = null): ShipmentResponseDTO;

    /**
     * Cancel an existing shipment.
     */
    public function cancelShipment(SalesOrderShipment $shipment): bool;

    /**
     * Retrieve shipment details from the provider.
     */
    public function getShipment(string $externalShipmentId): ShipmentResponseDTO;
}
