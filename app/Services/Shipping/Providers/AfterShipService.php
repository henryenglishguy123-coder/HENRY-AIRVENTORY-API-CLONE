<?php

namespace App\Services\Shipping\Providers;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\DTOs\Shipping\ShipmentResponseDTO;
use App\Enums\Shipping\ShippingProviderEnum;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Currency\Currency;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\Models\Shipping\ShippingPartner;
use App\Services\Shipping\ShippingPayloadBuilder;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfterShipService implements ShippingProviderInterface
{
    private const HTTP_TIMEOUT = 30;

    private const CUSTOMS_REQUIRED_ERROR_CODES = [
        'YUNEXPRESS_MISSING_CUSTOMS',
        'CUSTOMS_DECLARATION_REQUIRED',
        'MISSING_CUSTOMS_INFO',
        'CUSTOMS_INFORMATION_NEEDED',
    ];

    private array $yunexpressServiceTypes;

    private string $defaultServiceType;

    private array $yunexpressErrorCodes;

    private array $defaultDimensions;

    private string $defaultCurrency;

    private string $defaultCountryOfOrigin;

    private array $defaultCustomsPurposeOptions;

    private array $defaultTermsOfTradeOptions;

    private ShippingPartner $partner;

    private string $apiKey;

    private string $baseUrl;

    public function __construct(ShippingPartner $partner)
    {
        $this->partner = $partner;

        // Initialize dynamic configuration first
        $this->initializeDynamicConfig();

        $this->validatePartnerConfiguration($partner);
        $apiKey = $partner->api_key ?? config('services.aftership.api_key');
        if (! $apiKey) {
            Log::error('AfterShipService: No API key configured for partner.', [
                'partner_id' => $partner->id,
                'partner_code' => $partner->code,
                'partner_name' => $partner->name ?? 'N/A',
            ]);
            throw new \InvalidArgumentException('AfterShip API key is missing. Configure it on the shipping partner or via config.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = $partner->api_base_url ?? config('services.aftership.base_url', 'https://api.aftership.com');
    }

    private function initializeDynamicConfig(): void
    {
        $this->yunexpressServiceTypes = config('services.aftership.yunexpress.service_types', [
            'yunexpress_america_direct_line_standard_non_battery',
            'yunexpress_dhl_express',
            'yunexpress_direct_line_for_apprael_tracked',
            'yunexpress_direct_line_track',
            'yunexpress_direct_line_tracked_au_post',
            'yunexpress_direct_line_tracked_dg_goods',
            'yunexpress_domestic_express',
            'yunexpress_epacket_sz_branch',
            'yunexpress_global_direct_line_standard_battery',
            'yunexpress_global_direct_line_standard_track',
            'yunexpress_global_direct_line_tracked_battery',
            'yunexpress_global_direct_line_tracked_non_battery',
            'yunexpress_jp_direct_line_track',
            'yunexpress_middle_east_direct_line_ddp',
            'yunexpress_middle_east_direct_line_track',
            'yunexpress_us_direct_line_tracked_remote_area',
            'yunexpress_zj_direct_line_track',
        ]);

        $this->defaultServiceType = config('services.aftership.yunexpress.default_service_type', 'yunexpress_domestic_express');

        $this->yunexpressErrorCodes = config('services.aftership.yunexpress_error_codes', [
            'YUNEXPRESS_INVALID_ADDRESS' => 'Invalid address provided',
            'YUNEXPRESS_INSUFFICIENT_FUNDS' => 'Insufficient funds for shipment',
            'YUNEXPRESS_RESTRICTED_ITEM' => 'Restricted item detected',
            'YUNEXPRESS_MISSING_CUSTOMS' => 'Missing required customs information',
            'YUNEXPRESS_UNDELIVERABLE_DESTINATION' => 'Undeliverable destination',
            'CUSTOMS_DECLARATION_REQUIRED' => 'Customs declaration required for international shipment',
            'MISSING_CUSTOMS_INFO' => 'Missing customs information',
            'CUSTOMS_INFORMATION_NEEDED' => 'Customs information required for this shipment',
        ]);

        $this->defaultDimensions = config('shipping.default_dimensions', [
            'width' => 10,
            'height' => 10,
            'depth' => 10,
            'unit' => 'cm',
        ]);

        $this->defaultCurrency = Currency::getDefaultCurrency()->code ?? 'USD';

        $this->defaultCountryOfOrigin = config('shipping.default_origin_country', 'CN');

        $this->defaultCustomsPurposeOptions = config('shipping.customs.purpose_options', [
            'merchandise', 'gift', 'document', 'return_merchandise', 'sample',
        ]);

        $this->defaultTermsOfTradeOptions = config('shipping.customs.terms_of_trade_options', [
            'ddu', 'ddp', 'cpt', 'cip',
        ]);
    }

    /**
     * Validate partner configuration before initialization.
     */
    private function validatePartnerConfiguration(ShippingPartner $partner): void
    {
        if ($partner->code !== ShippingProviderEnum::AfterShip->value) {
            Log::warning('AfterShipService: Partner code mismatch', [
                'expected' => ShippingProviderEnum::AfterShip->value,
                'actual' => $partner->code,
            ]);
        }

        // Always validate as YunExpress since we're only supporting YunExpress
        Log::info('YunExpress service type enforced', [
            'partner_id' => $partner->id,
        ]);

        // Validate YunExpress-specific requirements
        $this->validateYunExpressRequirements($partner);
    }

    /**
     * Validates YunExpress-specific requirements for the partner configuration.
     */
    private function validateYunExpressRequirements(ShippingPartner $partner): void
    {
        // Ensure app_id is set for YunExpress
        if (empty($partner->app_id)) {
            Log::error('YunExpress requires app_id to be set on the shipping partner', [
                'partner_id' => $partner->id,
                'partner_name' => $partner->name,
            ]);

            throw new Exception('YunExpress requires app_id to be configured on the shipping partner');
        }

        // Always use YunExpress service type - ignore any other service type settings
        $serviceType = $partner->settings['service_type'] ?? $this->defaultServiceType;

        // Log shipper account information
        Log::info('YunExpress shipper account validation', [
            'partner_id' => $partner->id,
            'app_id' => $partner->app_id,
            'service_type' => $serviceType,
        ]);

        // If service type is not in YunExpress list, force it to default
        if (! in_array($serviceType, $this->yunexpressServiceTypes)) {
            Log::warning('Service type not in YunExpress list, forcing to default', [
                'original_service_type' => $serviceType,
                'forced_service_type' => $this->defaultServiceType,
                'partner_id' => $partner->id,
            ]);

            // Update the partner settings to use YunExpress service type
            $partner->settings = array_merge($partner->settings ?? [], [
                'service_type' => $this->defaultServiceType,
            ]);
            $serviceType = $this->defaultServiceType;
        }

        Log::info('YunExpress service type validated', [
            'service_type' => $serviceType,
            'partner_id' => $partner->id,
        ]);
    }

    /**
     * Check if the service type is related to YunExpress.
     */
    private function isYunExpressServiceType(?string $serviceType): bool
    {
        // Always return true since we're only supporting YunExpress
        return true;
    }

    /**
     * Extract specific error message for YunExpress related errors.
     */
    private function extractYunExpressError(array $responseData, string $serviceType): ?string
    {
        $errors = $responseData['data']['errors'] ?? $responseData['meta']['errors'] ?? [];
        $message = $responseData['meta']['message'] ?? '';

        foreach ($errors as $error) {
            if (isset($error['code'])) {
                $errorCode = strtoupper($error['code']);
                if (isset($this->yunexpressErrorCodes[$errorCode])) {
                    $errorMessage = isset($error['message']) ? $error['message'] : $message;

                    return $this->yunexpressErrorCodes[$errorCode].": {$errorMessage}";
                }
            }
        }

        // Check for custom error codes that indicate customs issues
        foreach (self::CUSTOMS_REQUIRED_ERROR_CODES as $customsErrorCode) {
            if (str_contains(strtoupper($message), strtoupper($customsErrorCode))) {
                return 'YunExpress Customs Error: Missing required customs information for international shipment';
            }
        }

        $lowerMessage = strtolower($message);

        // Handle service type validation errors - always treat as YunExpress
        if (str_contains($lowerMessage, 'service_type') && (str_contains($lowerMessage, 'equal to one of values') || str_contains($lowerMessage, 'should be equal to one of values'))) {
            return "YunExpress Service Error: Invalid service type configuration. Using default service type: {$this->defaultServiceType}. Error: {$message}";
        }

        // Handle carrier-specific errors
        if (str_contains($lowerMessage, 'carrier')) {
            return "YunExpress Carrier Error: {$message}";
        }

        if (str_contains($lowerMessage, 'yunexpress') || str_contains($lowerMessage, 'yune')) {
            return "YunExpress Error: {$message}";
        }

        // Check for customs-related errors in the message
        if (str_contains($lowerMessage, 'customs') || str_contains($lowerMessage, 'declaration') || str_contains($lowerMessage, 'invoice')) {
            return "Customs Error: {$message}";
        }

        return null;
    }

    public function createShipment(SalesOrder $order, ?string $idempotencyKey = null): ShipmentResponseDTO
    {
        $baseData = ShippingPayloadBuilder::build($order);

        if (empty($baseData['ship_from']) || empty($baseData['ship_to'])) {
            throw new Exception('ShippingPayloadBuilder returned incomplete data.');
        }

        // Always use YunExpress service type - ignore any other service type settings
        $serviceType = $this->partner->settings['service_type'] ?? $this->defaultServiceType;

        // Ensure service type is valid YunExpress service
        if (! in_array($serviceType, $this->yunexpressServiceTypes)) {
            Log::warning('Invalid service type detected, forcing to default YunExpress service', [
                'invalid_service_type' => $serviceType,
                'using_service_type' => $this->defaultServiceType,
                'order_id' => $order->id,
            ]);
            $serviceType = $this->defaultServiceType;
        }

        $parcelItems = [];

        foreach ($baseData['items'] ?? [] as $item) {
            $parcelItems[] = [
                'description' => $item['name'] ?? 'Item',
                'quantity' => $item['quantity'] ?? 1,
                'price' => [
                    'currency' => Currency::getDefaultCurrency()->code ?? 'USD',
                    'amount' => $item['price'] ?? 0,
                ],
                'sku' => $item['sku'] ?? '',
                'weight' => [
                    'unit' => 'kg',
                    'value' => $item['unit_weight_kg'] ?? 0.1,
                ],
                // Add HS code for customs purposes
                'hs_code' => $item['hs_code'] ?? '',
            ];
        }

        // Determine if customs data is required based on origin/destination countries
        $requiresCustoms = $this->requiresCustomsDeclaration($baseData['ship_from']['country'], $baseData['ship_to']['country']);

        // For YunExpress, always include customs data for international shipments
        // For domestic shipments, check configuration
        if (! $requiresCustoms) {
            $requiresCustoms = config('services.aftership.yunexpress.requires_customs', false);
        }

        $payload = [
            'return_shipment' => false,
            'is_document' => false,
            'service_type' => $serviceType,
            'order_number' => $order->order_number ?? (string) $order->id,
            'paper_size' => config('services.aftership.yunexpress.paper_size'),
            'references' => [
                $order->order_number ?? (string) $order->id,
            ],
            'shipment' => [
                'ship_from' => array_filter([
                    'contact_name' => $baseData['ship_from']['name'],
                    'company_name' => $baseData['ship_from']['company'],
                    'street1' => $baseData['ship_from']['street1'],
                    'street2' => $baseData['ship_from']['street2'] ?? '',
                    'street3' => $baseData['ship_from']['street3'] ?? '',
                    'city' => $baseData['ship_from']['city'],
                    'state' => $baseData['ship_from']['state'],
                    'postal_code' => $baseData['ship_from']['postal_code'],
                    'country' => $baseData['ship_from']['country'],
                    'phone' => $baseData['ship_from']['phone'],
                    'email' => $baseData['ship_from']['email'],
                ], fn ($val) => $val !== '' && $val !== null),
                'ship_to' => array_filter([
                    'contact_name' => $baseData['ship_to']['name'],
                    'street1' => $baseData['ship_to']['street1'],
                    'street2' => $baseData['ship_to']['street2'] ?? '',
                    'street3' => $baseData['ship_to']['street3'] ?? '',
                    'city' => $baseData['ship_to']['city'],
                    'state' => $baseData['ship_to']['state'],
                    'postal_code' => $baseData['ship_to']['postal_code'],
                    'country' => $baseData['ship_to']['country'],
                    'phone' => $baseData['ship_to']['phone'],
                    'email' => $baseData['ship_to']['email'],
                ], fn ($val) => $val !== '' && $val !== null),
                'parcels' => [
                    [
                        'box_type' => 'custom',
                        'dimension' => [
                            'width' => $baseData['items'][0]['width'] ?? $this->defaultDimensions['width'],
                            'height' => $baseData['items'][0]['height'] ?? $this->defaultDimensions['height'],
                            'depth' => $baseData['items'][0]['depth'] ?? $this->defaultDimensions['depth'],
                            'unit' => $this->defaultDimensions['unit'],
                        ],
                        'description' => 'Order Items',
                        'weight' => [
                            'value' => max(0.1, $baseData['total_weight_kg']),
                            'unit' => 'kg',
                        ],
                        'items' => $parcelItems,
                    ],
                ],
            ],

            'shipper_account' => [
                'id' => $this->partner->app_id,
            ],
        ];

        // Add customs information for international shipments
        if ($requiresCustoms) {
            $payload['customs'] = $this->buildCustomsData($baseData, $order);
        }

        $endpoint = $this->getEndpoint('/labels');

        // Restore the full payload logging for verification
        Log::info('AfterShip Full Payload', [
            'endpoint' => $endpoint,
            'order_id' => $order->id,
            'service_type' => $serviceType,
            'shipper_account_id' => $this->partner->app_id,
            'payload' => $payload,
        ]);

        $headers = [
            'as-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($idempotencyKey) {
            $headers['as-idempotency-key'] = $idempotencyKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(self::HTTP_TIMEOUT)
            ->post($endpoint, $payload);

        $shipmentData = $response->json();

        $metaCode = $shipmentData['meta']['code'] ?? null;

        // Handle different response codes according to AfterShip API specification
        if ($metaCode === 3001) {
            // Async processing - request accepted but not completed
            Log::info('AfterShip label creation initiated asynchronously', [
                'order_id' => $order->id,
                'service_type' => $serviceType,
                'response_data' => $shipmentData,
                'requires_customs' => $requiresCustoms,
            ]);

            // Extract label ID for polling
            $labelId = $shipmentData['data']['id'] ?? null;
            if (! $labelId) {
                throw new Exception('Async label creation response missing label ID');
            }

            // Dispatch job to check label status asynchronously
            // This would be called after the shipment record is created in the calling code
            // For now, return a DTO indicating async processing
            return new ShipmentResponseDTO(
                tracking_number: null,
                tracking_url: null,
                label_url: null,
                waybill_number: $labelId,
                cost: 0,
                weight: (float) $baseData['total_weight_kg'],
                raw_payload: $shipmentData,
                status: 'creating'
            );
        } elseif ($metaCode !== 200 && $metaCode !== 201) {
            // Extract and log YunExpress-specific error for actual errors
            $yunExpressError = $this->extractYunExpressError($shipmentData, $serviceType);

            $errorMessage = $yunExpressError ?? 'AfterShip API Error: '.($shipmentData['meta']['message'] ?? 'Unknown error');

            throw new ShippingProviderException($errorMessage, (int) $metaCode, $shipmentData);
        }

        // Successful synchronous response
        $label = $shipmentData['data']['label'];

        return new ShipmentResponseDTO(
            tracking_number: $label['tracking_numbers'][0],
            tracking_url: config('services.aftership.tracking_url').$label['tracking_numbers'][0],
            label_url: $label['files']['label']['url'] ?? null,
            waybill_number: (string) $label['id'],
            cost: (float) ($label['rate']['price'] ?? 0),
            weight: (float) $baseData['total_weight_kg'],
            raw_payload: $label
        );
    }

    /**
     * Determines if customs declaration is required based on origin and destination countries.
     */
    private function requiresCustomsDeclaration(string $originCountry, string $destinationCountry): bool
    {
        // Customs is required for international shipments
        return strtoupper($originCountry) !== strtoupper($destinationCountry);
    }

    /**
     * Builds customs data for international shipments.
     */
    private function buildCustomsData(array $baseData, SalesOrder $order): array
    {
        return [
            'purpose' => config('shipping.customs.purpose', 'merchandise'),
            'terms_of_trade' => config('shipping.customs.terms_of_trade', 'ddu'),
            'billing' => [
                'paid_by' => config('shipping.customs.billing_paid_by', 'recipient'),
            ],
        ];
    }

    public function cancelShipment(SalesOrderShipment $shipment): bool
    {
        if (! $shipment->waybill_number) {
            Log::warning('Cannot cancel shipment without waybill number', [
                'shipment_id' => $shipment->id,
                'order_id' => $shipment->sales_order_id,
            ]);

            return false;
        }

        $endpoint = $this->getEndpoint('/cancel-labels');

        $payload = [
            'label' => [
                'id' => $shipment->waybill_number,
            ],
        ];

        Log::info('AfterShip Cancel Request', [
            'endpoint' => $endpoint,
            'shipment_id' => $shipment->id,
            'waybill_number' => $shipment->waybill_number,
            'payload' => $payload,
        ]);

        $response = Http::withHeaders([
            'as-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::HTTP_TIMEOUT)
            ->post($endpoint, $payload);

        $cancelBody = $response->json();

        if ($response->failed()) {
            $status = $response->status();

            // Handle 409 Conflict: Already shipped or already cancelled
            if ($status === 409) {
                Log::warning('AfterShip Cancel Conflict (409): Label already shipped or cancelled', [
                    'shipment_id' => $shipment->id,
                    'waybill_number' => $shipment->waybill_number,
                    'response' => $cancelBody,
                ]);

                // We treat this as a success for the internal workflow as the target state (cancelled/voided)
                // is either already met or is unreachable.
                return true;
            }

            $logContext = [
                'shipment_id' => $shipment->id,
                'waybill_number' => $shipment->waybill_number,
                'response_status' => $status,
                'response_body' => $cancelBody,
                'order_id' => $shipment->sales_order_id,
            ];

            if (config('app.env') !== 'production') {
                $logContext['raw_response'] = $cancelBody;
            }

            Log::error('AfterShip Cancel Error', $logContext);

            $cancelMsg = is_array($cancelBody)
                ? ($cancelBody['meta']['message'] ?? json_encode($cancelBody))
                : ($cancelBody ?: 'Could not cancel label');

            // Check for YunExpress-specific errors
            $yunExpressError = $this->extractYunExpressError($cancelBody, 'cancel');

            if ($yunExpressError) {
                $cancelMsg = $yunExpressError;
            } elseif (isset($cancelBody['meta']['message']) && str_contains(strtolower($cancelBody['meta']['message']), 'yunexpress')) {
                $cancelMsg = 'YunExpress Cancel Error: '.$cancelBody['meta']['message'];
            }

            throw new ShippingProviderException('AfterShip Cancel Error: '.$cancelMsg, $status, $cancelBody);
        }

        Log::info('Successfully canceled shipment', [
            'shipment_id' => $shipment->id,
            'waybill_number' => $shipment->waybill_number,
            'order_id' => $shipment->sales_order_id,
            'response' => $cancelBody,
        ]);

        return true;
    }

    /**
     * Retrieve shipment details from AfterShip.
     */
    public function getShipment(string $externalShipmentId): ShipmentResponseDTO
    {
        $endpoint = $this->getEndpoint('/labels/'.rawurlencode($externalShipmentId));

        $response = Http::withHeaders([
            'as-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::HTTP_TIMEOUT)
            ->get($endpoint);

        $shipmentData = $response->json();

        if ($response->failed() || ($shipmentData['meta']['code'] ?? 0) >= 400) {
            return new ShipmentResponseDTO(
                tracking_number: null,
                tracking_url: null,
                label_url: null,
                waybill_number: $externalShipmentId,
                cost: 0,
                weight: 0,
                raw_payload: $shipmentData,
                status: 'failed'
            );
        }

        $data = $shipmentData['data'];
        $status = $data['status'] ?? null;

        if ($status === 'completed' && isset($data['label'])) {
            $label = $data['label'];

            return new ShipmentResponseDTO(
                tracking_number: $label['tracking_numbers'][0] ?? null,
                tracking_url: isset($label['tracking_numbers'][0]) ? config('services.aftership.tracking_url').$label['tracking_numbers'][0] : null,
                label_url: $label['files']['label']['url'] ?? null,
                waybill_number: (string) ($label['id'] ?? $externalShipmentId),
                cost: (float) ($label['rate']['price'] ?? 0),
                weight: (float) ($data['shipment']['total_weight'] ?? 0),
                raw_payload: $label,
                status: 'completed'
            );
        }

        return new ShipmentResponseDTO(
            tracking_number: null,
            tracking_url: null,
            label_url: null,
            waybill_number: $externalShipmentId,
            cost: 0,
            weight: 0,
            raw_payload: $data,
            status: $status
        );
    }

    /**
     * Build the full API endpoint for V3.
     */
    private function getEndpoint(string $path): string
    {
        $baseUrl = rtrim($this->baseUrl, '/');

        // Robustly ensure we have the /postmen/v3 path prefix
        if (! str_contains($baseUrl, '/postmen/v3')) {
            $baseUrl .= '/postmen/v3';
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }
}
