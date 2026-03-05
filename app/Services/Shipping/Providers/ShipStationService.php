<?php

namespace App\Services\Shipping\Providers;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\DTOs\Shipping\ShipmentResponseDTO;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\Models\Shipping\ShippingPartner;
use App\Services\Shipping\ShippingPayloadBuilder;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipStationService implements ShippingProviderInterface
{
    private const HTTP_TIMEOUT = 30;

    private ShippingPartner $partner;

    private string $apiKey;

    private string $baseUrl;

    public function __construct(ShippingPartner $partner)
    {
        $this->partner = $partner;
        $apiKey = $partner->api_key ?? config('services.shipstation.api_key');

        if (! $apiKey) {
            Log::error('ShipStationService: No API key configured for partner.', ['partner_id' => $partner->id]);
            throw new \InvalidArgumentException('ShipStation API key is missing. Configure it on the shipping partner or via config.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = $partner->api_base_url ?? config('services.shipstation.base_url', 'https://ssapi.shipstation.com');
    }

    public function createShipment(SalesOrder $order, ?string $idempotencyKey = null): ShipmentResponseDTO
    {
        $baseData = ShippingPayloadBuilder::build($order);

        // Map to ShipStation V2 format
        $shipTo = array_filter([
            'name' => $baseData['ship_to']['name'],
            'company_name' => $baseData['ship_to']['company'],
            'address_line1' => $baseData['ship_to']['street1'],
            'address_line2' => $baseData['ship_to']['street2'],
            'address_line3' => $baseData['ship_to']['street3'] ?? '',
            'city_locality' => $baseData['ship_to']['city'],
            'state_province' => $baseData['ship_to']['state'],
            'postal_code' => $baseData['ship_to']['postal_code'],
            'country_code' => $baseData['ship_to']['country'],
            'phone' => $baseData['ship_to']['phone'],
            'email' => $baseData['ship_to']['email'],
            'address_residential_indicator' => 'unknown',
        ], fn ($val) => $val !== '' && $val !== null);

        $shipFrom = array_filter([
            'name' => $baseData['ship_from']['name'],
            'company_name' => $baseData['ship_from']['company'],
            'address_line1' => $baseData['ship_from']['street1'],
            'address_line2' => $baseData['ship_from']['street2'] ?? '',
            'address_line3' => $baseData['ship_from']['street3'] ?? '',
            'city_locality' => $baseData['ship_from']['city'],
            'state_province' => $baseData['ship_from']['state'],
            'postal_code' => $baseData['ship_from']['postal_code'],
            'country_code' => $baseData['ship_from']['country'],
            'phone' => $baseData['ship_from']['phone'],
            'email' => $baseData['ship_from']['email'],
            'address_residential_indicator' => 'no',
        ], fn ($val) => $val !== '' && $val !== null);

        $items = [];
        $totalWeightKgs = $baseData['total_weight_kg'];

        $customsItems = [];

        // Resolve country_of_origin from config or throw
        $originCountry = $shipFrom['country_code'] ?? null;
        if (! $originCountry) {
            $configDefault = config('shipping.default_origin_country');
            if (! $configDefault) {
                throw new \InvalidArgumentException('ShipStation: country_of_origin could not be determined. Set shipping.default_origin_country or ensure factory has a country set.');
            }
            $originCountry = $configDefault;
        }

        foreach ($baseData['items'] as $item) {
            $originalPrice = $item['price'] ?? 0;
            $originalWeight = $item['unit_weight_kg'] ?? 0;
            $sku = $item['sku'] ?? 'N/A';

            $normalizedPrice = \App\Services\Shipping\ShippingPriceNormalizer::normalizeItemPrice($originalPrice, 'shipstation');
            $normalizedWeightKg = \App\Services\Shipping\ShippingPriceNormalizer::normalizeWeight($originalWeight, 'kg', 'shipstation');

            if ($normalizedPrice !== (float) $originalPrice) {
                \App\Services\Shipping\ShippingPriceNormalizer::logNormalization($sku, $originalPrice, $normalizedPrice, 'price', 'shipstation');
            }

            if ($normalizedWeightKg !== (float) $originalWeight) {
                \App\Services\Shipping\ShippingPriceNormalizer::logNormalization($sku, $originalWeight, $normalizedWeightKg, 'weight', 'shipstation');
            }

            $weightGrams = $normalizedWeightKg * 1000;

            $items[] = array_filter([
                'description' => $item['name'],
                'sku' => $sku,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'weight' => [
                    'value' => $weightGrams,
                    'unit' => 'gram',
                ],
                'value' => [
                    'currency' => 'usd',
                    'amount' => $normalizedPrice,
                ],
            ], fn ($val) => $val !== '' && $val !== null);

            $customsItems[] = [
                'description' => substr($item['name'], 0, 50),
                'quantity' => max(1, $item['quantity']),
                'value' => [
                    'currency' => 'usd',
                    'amount' => $normalizedPrice,
                ],
                'country_of_origin' => $originCountry,
            ];
        }

        $shipDate = now()->toISOString();
        $settings = $this->partner->settings ?? [];
        $carrierId = $settings['carrier_id'] ?? config('services.shipstation.carrier_id', 'stamps_com');
        $serviceCode = $settings['service_code'] ?? config('services.shipstation.service_code', 'usps_priority_mail');

        // Read dimensions from config or use sensible defaults
        $defaultDimensions = config('shipping.default_dimensions', [
            'length' => 5,
            'width' => 5,
            'height' => 5,
            'unit' => 'inch',
        ]);

        $totalWeightGrams = $totalWeightKgs * 1000;
        if ($totalWeightGrams < 1) {
            Log::warning("ShipStationService: Total package weight ({$totalWeightGrams}g) is less than 1 gram. Using minimum 1 gram to prevent API errors.", ['order_number' => $baseData['order_number']]);
            $totalWeightGrams = 1;
        }

        $payload = [
            'shipment' => [
                'carrier_id' => $carrierId,
                'service_code' => $serviceCode,
                'ship_date' => $shipDate,
                'ship_to' => $shipTo,
                'ship_from' => $shipFrom,
                'customs' => [
                    'contents' => 'merchandise',
                    'non_delivery' => 'return_to_sender',
                    'customs_items' => $customsItems,
                ],
                'packages' => [
                    [
                        'weight' => [
                            'value' => $totalWeightGrams, // Converted to grams, ensure > 1
                            'unit' => 'gram',
                        ],
                        'dimensions' => [
                            'length' => (float) ($defaultDimensions['length'] ?? 0),
                            'width' => (float) ($defaultDimensions['width'] ?? 0),
                            'height' => (float) ($defaultDimensions['height'] ?? 0),
                            'unit' => $this->mapDimensionUnit($defaultDimensions['unit'] ?? 'inch'),
                        ],
                    ],
                ],
            ],
        ];

        $headers = [
            'API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(self::HTTP_TIMEOUT)
            ->post($this->baseUrl.'/v2/labels', $payload);

        $shipmentData = $response->json();

        if ($response->failed()) {
            $errorMessage = 'Unknown error';
            if (isset($shipmentData['errors'])) {
                $errorMessage = is_array($shipmentData['errors']) ? json_encode($shipmentData['errors']) : $shipmentData['errors'];
            } elseif (isset($shipmentData['message'])) {
                $errorMessage = $shipmentData['message'];
            }

            throw new ShippingProviderException('ShipStation API Error: '.$errorMessage, $response->status(), $shipmentData);
        }

        // Fail fast when required fields are missing from the response
        if (empty($shipmentData['tracking_number']) || empty($shipmentData['shipment_id'])) {
            Log::error('ShipStation: missing required fields in API response', [
                'missing' => array_filter(['tracking_number' => empty($shipmentData['tracking_number']), 'shipment_id' => empty($shipmentData['shipment_id'])]),
                'raw_data' => $shipmentData,
            ]);
            throw new Exception('ShipStation API returned an incomplete response: tracking_number or shipment_id is missing.');
        }

        // Strip PII (addresses) from raw payload to avoid storing sensitive user data
        $safePayload = $shipmentData;
        unset($safePayload['ship_to'], $safePayload['ship_from']);

        return new ShipmentResponseDTO(
            tracking_number: $shipmentData['tracking_number'],
            tracking_url: $shipmentData['tracking_url'] ?? ('https://track.shipstation.com/'.rawurlencode($shipmentData['tracking_number'])),
            label_url: $shipmentData['label_download']['pdf'] ?? null,
            waybill_number: (string) $shipmentData['shipment_id'],
            cost: is_numeric($shipmentData['shipment_cost'] ?? null) ? (float) $shipmentData['shipment_cost'] : null,
            weight: (float) $totalWeightKgs,
            raw_payload: $safePayload,
            shipment_id: (string) $shipmentData['shipment_id'],
            label_id: isset($shipmentData['label_id']) ? (string) $shipmentData['label_id'] : null
        );
    }

    public function cancelShipment(SalesOrderShipment $shipment): bool
    {
        $shipmentId = $shipment->external_shipment_id ?? $shipment->waybill_number;
        $labelId = $shipment->label_id;

        if (! $labelId || ! $shipmentId) {
            // Try to recover from raw_payload in tracking logs if missing
            $initialLog = $shipment->trackingLogs()->where('status', \App\Enums\Shipping\ShipmentStatusEnum::CREATING->value)->first();
            if ($initialLog && is_array($initialLog->raw_payload)) {
                $payload = $initialLog->raw_payload;
                $labelId = $labelId ?: ($payload['label_id'] ?? null);
                $shipmentId = $shipmentId ?: ($payload['shipment_id'] ?? null);
            }
        }

        if (! $shipmentId) {
            return false;
        }

        // 1. Void label
        if ($labelId) {
            $response = Http::withHeaders([
                'API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(self::HTTP_TIMEOUT)
                ->put($this->baseUrl.'/v2/labels/'.rawurlencode($labelId).'/void');

            if ($response->failed()) {
                $status = $response->status();
                $payload = $response->json();
                $detail = empty($payload) ? 'Could not void label' : json_encode($payload);

                if (in_array($status, [404, 410], true)) {
                    // Non-blocking: label is already voided or does not exist.
                    // We can safely proceed to cancel the shipment.
                    Log::warning('ShipStation Void Non-blocking', [
                        'shipment_id' => $shipment->id,
                        'label_id' => $labelId,
                        'http_status' => $status,
                        'detail' => $detail,
                    ]);
                } else {
                    Log::error('ShipStation Void Error', [
                        'shipment_id' => $shipment->id,
                        'label_id' => $labelId,
                        'http_status' => $status,
                        'detail' => $detail,
                    ]);
                    // Halting: do NOT proceed to cancel while the label has not been voided.
                    // Continuing would risk the service thinking the shipment is cancelled
                    // while the carrier still has an active label.
                    throw new Exception('ShipStation Void Error (label_id: '.$labelId.'): '.$detail);
                }
            }
        }

        // 2. Cancel shipment
        $response = Http::withHeaders([
            'API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::HTTP_TIMEOUT)
            ->put($this->baseUrl.'/v2/shipments/'.rawurlencode($shipmentId).'/cancel');

        if ($response->failed()) {
            $payload = $response->json();
            $detail = empty($payload) ? 'Could not cancel shipment' : json_encode($payload);
            Log::error('ShipStation Cancel Error', ['shipment_id' => $shipment->id, 'detail' => $detail]);
            throw new Exception('ShipStation Cancel Error: '.$detail);
        }

        return true;
    }

    /**
     * Retrieve shipment details from ShipStation.
     */
    public function getShipment(string $externalShipmentId): ShipmentResponseDTO
    {
        $response = Http::withHeaders([
            'API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::HTTP_TIMEOUT)
            ->get($this->baseUrl.'/v2/shipments/'.rawurlencode($externalShipmentId));

        if ($response->failed()) {
            throw new Exception('ShipStation API Get Shipment Error: '.($response->json()['message'] ?? 'Unknown error'));
        }

        $shipmentData = $response->json();

        return new ShipmentResponseDTO(
            tracking_number: $shipmentData['tracking_numbers'][0] ?? $shipmentData['tracking_number'] ?? null,
            tracking_url: $shipmentData['tracking_url'] ?? null,
            label_url: $shipmentData['label_download']['pdf'] ?? null,
            waybill_number: (string) ($shipmentData['shipment_id'] ?? $externalShipmentId),
            cost: is_numeric($shipmentData['shipment_cost'] ?? null) ? (float) $shipmentData['shipment_cost'] : null,
            weight: isset($shipmentData['weight']) ? (float) ($shipmentData['weight']['value'] ?? 0) : null,
            raw_payload: $shipmentData,
            shipment_id: (string) ($shipmentData['shipment_id'] ?? null)
        );
    }

    /**
     * Maps internal dimension units to ShipStation/ShipEngine supported units.
     */
    private function mapDimensionUnit(string $unit): string
    {
        return match (strtolower($unit)) {
            'cm', 'centimeters', 'centimeter' => 'centimeter',
            'in', 'inches', 'inch' => 'inch',
            default => 'inch',
        };
    }
}
