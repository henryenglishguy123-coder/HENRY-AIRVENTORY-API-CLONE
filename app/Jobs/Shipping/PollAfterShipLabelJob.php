<?php

namespace App\Jobs\Shipping;

use App\Enums\Order\OrderStatus;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\Services\Shipping\ShippingProviderManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollAfterShipLabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 10;

    /**
     * The number of seconds to wait before retrying the job.
     * We use a progressive delay for polling.
     */
    public $backoff = [10, 20, 30, 60, 120, 300, 600];

    public function __construct(
        public SalesOrderShipment $shipment,
        public string $externalShipmentId
    ) {}

    public function handle(): void
    {
        try {
            $order = $this->shipment->order;
            $factory = $order->factory;
            $partner = $factory->shippingPartners()->first();

            if (! $partner) {
                throw new Exception("Shipping partner not found for Order {$order->id}");
            }

            $provider = ShippingProviderManager::resolve($partner);
            $responseDTO = $provider->getShipment($this->externalShipmentId);

            Log::info("Polling AfterShip label status for Shipment {$this->shipment->id}: {$responseDTO->status}");

            if ($responseDTO->status === 'completed' && $responseDTO->tracking_number) {
                // Label is ready! Update the shipment record.
                $this->shipment->update([
                    'tracking_number' => $responseDTO->tracking_number,
                    'tracking_url' => $responseDTO->tracking_url,
                    'label_url' => $responseDTO->label_url,
                    'status' => ShipmentStatusEnum::COMPLETED->value,
                    'shipping_cost' => $responseDTO->cost,
                    'total_weight' => $responseDTO->weight,
                ]);

                // Log the success in tracking logs
                $this->shipment->trackingLogs()->create([
                    'status' => ShipmentStatusEnum::COMPLETED->value,
                    'checkpoint_time' => Carbon::now(),
                    'raw_payload' => $responseDTO->raw_payload,
                    'provider' => $partner->code ?? 'aftership',
                    'description' => 'Label created successfully by provider (async).',
                ]);

                Log::info("Shipment {$this->shipment->id} label retrieved and updated.");

                return;
            }

            if ($responseDTO->status === 'failed') {
                $this->shipment->trackingLogs()->create([
                    'status' => ShipmentStatusEnum::FAILED->value,
                    'checkpoint_time' => Carbon::now(),
                    'raw_payload' => $responseDTO->raw_payload,
                    'provider' => $partner->code ?? 'aftership',
                    'description' => 'Label creation failed at provider.',
                ]);

                // Also update the order status back to failed and log it in history
                $order->update(['order_status' => OrderStatus::Failed->value]);

                $order->statusHistory()->create([
                    'from_status' => OrderStatus::Shipped->value, // Or wherever it came from, but it was set to Shipped in the create job
                    'to_status' => OrderStatus::Failed->value,
                    'reason' => 'Async Shipment Creation Failed: '.($responseDTO->raw_payload['meta']['message'] ?? 'Unknown carrier error'),
                    'source' => 'system',
                    'full_payload' => $responseDTO->raw_payload,
                ]);

                Log::error("AfterShip Label creation failed for Shipment {$this->shipment->id}");

                return;
            }

            // Still creating or in other status, retry
            Log::info("AfterShip Label for Shipment {$this->shipment->id} is still in status: {$responseDTO->status}. Retrying later.");

            $this->release(60); // Release back to queue with delay

        } catch (Exception $e) {
            Log::error("Error polling AfterShip label for Shipment {$this->shipment->id}: ".$e->getMessage());
            throw $e;
        }
    }
}
