<?php

declare(strict_types=1);

namespace App\DTOs\Shipping;

readonly class ShipmentResponseDTO
{
    public function __construct(
        public ?string $tracking_number,
        public ?string $tracking_url,
        public ?string $label_url,
        public ?string $waybill_number,
        public ?float $cost,
        public ?float $weight,
        public ?array $raw_payload,
        public ?string $shipment_id = null,
        public ?string $label_id = null,
        public ?string $status = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tracking_number: $data['tracking_number'] ?? null,
            tracking_url: $data['tracking_url'] ?? null,
            label_url: $data['label_url'] ?? null,
            waybill_number: $data['waybill_number'] ?? null,
            cost: isset($data['cost']) && is_numeric($data['cost']) ? (float) $data['cost'] : null,
            weight: isset($data['weight']) && is_numeric($data['weight']) ? (float) $data['weight'] : null,
            raw_payload: $data['raw_payload'] ?? null,
            shipment_id: $data['shipment_id'] ?? null,
            label_id: $data['label_id'] ?? null,
            status: $data['status'] ?? null,
        );
    }
}
