<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Order\OrderStatus;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Shipping\TrackingUpdated;
use App\Http\Controllers\Controller;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShippingWebhookController extends Controller
{
    /**
     * Handle incoming tracking updates from a shipping provider.
     */
    public function handleTrackingUpdate(Request $request, string $provider)
    {
        // Verify webhook signature before processing
        $signatureResponse = $this->verifyWebhookSignature($request, $provider);
        if ($signatureResponse !== null) {
            return $signatureResponse;
        }

        try {
            DB::beginTransaction();
            $payload = $request->all();

            // Extract checkpoint timestamp from payload with safe fallback
            $rawCheckpointTime = $payload['msg']['checkpoints'][0]['checkpoint_time'] ?? null;
            $checkpointTime = null;
            if ($rawCheckpointTime) {
                try {
                    $checkpointTime = Carbon::parse($rawCheckpointTime);
                } catch (Exception) {
                    $checkpointTime = null;
                }
            }
            $checkpointTime = $checkpointTime ?? Carbon::now();

            // Based on provider, extract necessary data
            $trackingNumber = $payload['msg']['tracking_number'] ?? $payload['tracking_number'] ?? null;
            $eventId = $request->header('x-aftership-event') ?? $payload['event_id'] ?? uniqid();
            $rawStatus = $payload['msg']['tag'] ?? $payload['status'] ?? 'unknown';
            $location = $payload['msg']['checkpoints'][0]['location'] ?? null;
            $description = $payload['msg']['checkpoints'][0]['message'] ?? 'Update received';

            if (! $trackingNumber) {
                throw new Exception("Webhook from $provider missing tracking number.");
            }

            // Find shipment — return 200 acknowledgement if not found (avoid provider retries)
            $shipment = SalesOrderShipment::where('tracking_number', $trackingNumber)->first();

            if (! $shipment) {
                Log::warning("Shipping webhook: shipment not found for tracking number {$trackingNumber} from {$provider}.");

                return response()->json([
                    'success' => true,
                    'message' => 'Tracking number not found; acknowledged.',
                ], 200);
            }

            $order = $shipment->order;

            // Map provider status to internal ShipmentStatusEnum
            $mappedStatus = $this->mapProviderStatus($provider, $rawStatus);

            // Idempotency: skip if we already have a log with this provider_event_id
            $existingLog = $shipment->trackingLogs()
                ->where('provider', $provider)
                ->where('provider_event_id', $eventId)
                ->first();

            if ($existingLog) {
                return response()->json(['success' => true]);
            }

            // Log Tracking
            $trackingLog = $shipment->trackingLogs()->create([
                'status' => $mappedStatus,
                'sub_status' => $rawStatus,
                'description' => $description,
                'location' => $location,
                'checkpoint_time' => $checkpointTime,
                'raw_payload' => $payload,
                'provider' => $provider,
                'provider_event_id' => $eventId,
            ]);

            // Determine if Order status should change, considering all shipments
            $oldOrderStatus = $order->order_status;
            $newOrderStatus = $this->determineOrderStatus($order, $mappedStatus, $oldOrderStatus);

            if ($oldOrderStatus !== $newOrderStatus && $newOrderStatus !== null) {
                $order->statusHistory()->create([
                    'from_status' => $oldOrderStatus,
                    'to_status' => $newOrderStatus,
                    'source' => "webhook_{$provider}",
                    'shipment_id' => $shipment->id,
                    'reason' => "Status update received from {$provider}: {$mappedStatus}",
                    'admin_id' => null,
                ]);

                $order->update(['order_status' => $newOrderStatus]);

                event(new OrderStatusUpdated($order, $oldOrderStatus, $newOrderStatus));
            }

            DB::commit();

            // Fire tracking updated event regardless of whether order status changed
            event(new TrackingUpdated($trackingLog));

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Shipping Webhook Failed ($provider): ".$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Processing failed.',
            ], 500);
        }
    }

    /**
     * Verify the webhook signature for the given provider.
     * Returns null if valid, or an HTTP response if verification fails.
     */
    protected function verifyWebhookSignature(Request $request, string $provider)
    {
        $secret = config("shipping.webhooks.{$provider}.secret");

        // If no secret configured, skip verification (allows enabling per-provider)
        if (! $secret) {
            return null;
        }

        if ($provider === 'aftership') {
            $computedHmac = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
            $incomingHmac = $request->header('x-aftership-hmac-sha256', '');

            if (! hash_equals($computedHmac, $incomingHmac)) {
                Log::warning('AfterShip webhook signature mismatch.');

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            return null;
        }

        if ($provider === 'shipstation') {
            // ShipStation webhooks do not include HMAC signatures by default.
            // Note: This endpoint should be protected via infrastructure-level IP whitelisting
            // or by querying the ShipStation API to validate the payload upon receipt.
            return null;
        }

        // Unknown provider with a configured secret: fail-closed to prevent bypass.
        Log::warning("Shipping webhook: unknown provider '{$provider}' has a configured secret but no verification logic.");

        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
    }

    /**
     * Map provider-specific statuses to internal unified statuses.
     */
    protected function mapProviderStatus(string $provider, string $rawStatus): string
    {
        $status = strtolower($rawStatus);

        if ($provider === 'aftership') {
            return match ($status) {
                'pending' => ShipmentStatusEnum::Pending->value,
                'in_transit' => ShipmentStatusEnum::InTransit->value,
                'out_for_delivery' => ShipmentStatusEnum::OutForDelivery->value,
                'delivered' => ShipmentStatusEnum::Delivered->value,
                'exception' => ShipmentStatusEnum::Failed->value,
                default => (function () use ($provider, $status) {
                    Log::warning("Unrecognized shipping status '{$status}' from provider '{$provider}'. Falling back to InTransit.");

                    return ShipmentStatusEnum::InTransit->value;
                })(),
            };
        }

        Log::warning("Unhandled provider '{$provider}' with status '{$status}'. Falling back to InTransit.");

        return ShipmentStatusEnum::InTransit->value;
    }

    /**
     * Determine the new order status based on shipment tracking updates,
     * considering all shipments for multi-shipment orders and valid transitions.
     */
    protected function determineOrderStatus($order, string $shipmentStatus, string $currentOrderStatus): ?string
    {
        // Allowed state transitions: current => [allowed next states]
        // Terminal states (Delivered, Failed) map to [] — no further transitions permitted.
        $allowedTransitions = [
            OrderStatus::Confirmed->value      => [OrderStatus::InTransit->value, OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value],
            OrderStatus::Shipped->value        => [OrderStatus::InTransit->value, OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value, OrderStatus::Failed->value],
            OrderStatus::InTransit->value      => [OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value, OrderStatus::Failed->value],
            OrderStatus::OutForDelivery->value => [OrderStatus::Delivered->value, OrderStatus::Failed->value],
            OrderStatus::Delivered->value      => [], // terminal — no further transitions
            OrderStatus::Failed->value         => [], // terminal — no further transitions
        ];

        $targetOrderStatus = match ($shipmentStatus) {
            ShipmentStatusEnum::InTransit->value => OrderStatus::InTransit->value,
            ShipmentStatusEnum::OutForDelivery->value => OrderStatus::OutForDelivery->value,
            ShipmentStatusEnum::Delivered->value => OrderStatus::Delivered->value,
            ShipmentStatusEnum::Failed->value => null, // handled below per multi-shipment logic
            default => null,
        };

        // For "Delivered" and "Failed": use targeted queries instead of loading all
        // shipments and tracking logs into memory.
        if (in_array($shipmentStatus, [ShipmentStatusEnum::Delivered->value, ShipmentStatusEnum::Failed->value])) {
            if ($shipmentStatus === ShipmentStatusEnum::Delivered->value) {
                // Check if there exists any shipment without a "Delivered" tracking log.
                $hasUndeliveredShipments = $order->shipments()
                    ->whereDoesntHave('trackingLogs', function ($query) {
                        $query->where('status', ShipmentStatusEnum::Delivered->value);
                    })
                    ->exists();

                $targetOrderStatus = $hasUndeliveredShipments
                    ? OrderStatus::InTransit->value
                    : OrderStatus::Delivered->value;
            }

            if ($shipmentStatus === ShipmentStatusEnum::Failed->value) {
                $failedOrCancelled = [ShipmentStatusEnum::Failed->value, ShipmentStatusEnum::Cancelled->value];

                // Check if there exists any shipment without a Failed/Cancelled tracking log.
                $hasShipmentsNotFailedOrCancelled = $order->shipments()
                    ->whereDoesntHave('trackingLogs', function ($query) use ($failedOrCancelled) {
                        $query->whereIn('status', $failedOrCancelled);
                    })
                    ->exists();

                $targetOrderStatus = $hasShipmentsNotFailedOrCancelled
                    ? null
                    : OrderStatus::Failed->value;
            }
        }

        if ($targetOrderStatus === null) {
            return null;
        }

        // Validate transition
        $permitted = $allowedTransitions[$currentOrderStatus] ?? [];
        if (! in_array($targetOrderStatus, $permitted)) {
            return null;
        }

        return $targetOrderStatus;
    }
}
