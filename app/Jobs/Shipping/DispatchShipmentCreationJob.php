<?php

namespace App\Jobs\Shipping;

use App\Enums\Order\OrderStatus;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Events\Order\OrderShipped;
use App\Events\Order\OrderStatusUpdated;
use App\Exceptions\Shipping\ShippingProviderException;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Shipment\ShipmentSequence;
use App\Services\Shipping\ShippingProviderManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispatchShipmentCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is considered failed.
     */
    public int $tries = 1;

    /**
     * Timeout in seconds for a single job attempt.
     */
    public int $timeout = 120;

    /**
     * Backoff in seconds between retries.
     */
    public array $backoff = [30, 60];

    public function __construct(
        public SalesOrder $order,
        public int $adminId
    ) {}

    public function handle(): void
    {
        // Acquire a distributed mutex so only one worker can run the idempotency
        // check + provider API call at a time for a given order, preventing
        // TOCTOU races between concurrent queue workers.
        $lock = Cache::lock('shipment_creation_order_'.$this->order->id, 120);

        if (! $lock->get()) {
            // Another worker is already processing this order. Release and let
            // the queue retry naturally (not thrown, so job is not failed).
            Log::warning("DispatchShipmentCreationJob: could not acquire lock for Order {$this->order->id}, will retry.");
            $this->release(10); // release back to queue in 10 s

            return;
        }

        try {
            // Re-check inside the lock: if the order is already fully shipped,
            // there is nothing to do. This allows legitimate multiple/partial
            // shipments to be created while the order is not yet in a final
            // shipped state, avoiding an over-broad idempotency check.
            if ($this->order->order_status === OrderStatus::Shipped->value) {
                Log::info("DispatchShipmentCreationJob: order {$this->order->id} is already in SHIPPED status, skipping shipment creation.");

                return;
            }

            $factory = $this->order->factory;
            if (! $factory) {
                throw new Exception("Order {$this->order->id} has no factory assigned.");
            }

            $partner = $factory->shippingPartners()->first();
            if (! $partner) {
                throw new Exception("Factory for Order {$this->order->id} has no shipping partner assigned.");
            }

            // Ensure necessary relations are loaded for payload building
            $this->order->loadMissing([
                'factory.business.countryData',
                'factory.business.stateData',
                'addresses.countryData',
                'addresses.stateData',
                'items',
                'customer',
            ]);

            $providerCode = $partner->code ?? 'aftership';

            // Generate a stable idempotency key so if the job retries after a
            // network failure but the provider already processed the request,
            // the provider can deduplicate on its end.
            $idempotencyKey = hash('sha256', 'order_'.$this->order->id.'_'.$this->jobId());

            // Make the external provider API call OUTSIDE the DB transaction to avoid
            // holding DB connections during slow HTTP calls. If the call fails, nothing is committed.
            $provider = ShippingProviderManager::resolve($partner);
            $responseDTO = $provider->createShipment($this->order, $idempotencyKey);

            DB::beginTransaction();

            // Generate global sequential shipment number
            $shipmentNumber = $this->generateShipmentNumber();

            $shipment = $this->order->shipments()->create([
                'sales_shipment_number' => $shipmentNumber,
                'tracking_name' => $partner->name ?? ucfirst($providerCode),
                'tracking_number' => $responseDTO->tracking_number,
                'tracking_url' => $responseDTO->tracking_url,
                'label_url' => $responseDTO->label_url,
                'waybill_number' => $responseDTO->waybill_number,
                'external_shipment_id' => $responseDTO->shipment_id,
                'label_id' => $responseDTO->label_id,
                'status' => $responseDTO->status ?? ShipmentStatusEnum::PROCESSING->value,
                'shipping_cost' => $responseDTO->cost,
                'total_weight' => $responseDTO->weight,
                'total_quantity' => $this->order->items->sum('qty'),
            ]);

            // Shipment Items (Full Order)
            foreach ($this->order->items as $item) {
                $shipment->items()->create([
                    'sales_order_id' => $this->order->id,
                    'sales_order_item_id' => $item->id,
                    'sales_order_item_name' => $item->product_name,
                    'sales_order_item_sku' => $item->sku,
                    'quantity' => $item->qty,
                ]);
            }

            // Shipment Addresses — only pass allowed fields, not id/timestamps
            $addressFields = [
                'first_name', 'last_name', 'phone', 'email',
                'address_line_1', 'address_line_2', 'city',
                'state_id', 'state', 'postal_code', 'country_id', 'country',
            ];

            $billing = $this->order->billingAddress;
            if ($billing) {
                $shipment->addresses()->create(
                    array_merge($billing->only($addressFields), ['address_type' => 'billing'])
                );
            }

            $shipping = $this->order->shippingAddress;
            if ($shipping) {
                $shipment->addresses()->create(
                    array_merge($shipping->only($addressFields), ['address_type' => 'shipping'])
                );
            }

            // Initial Tracking Log
            $shipment->trackingLogs()->create([
                'status' => $responseDTO->status ?? ShipmentStatusEnum::COMPLETED->value,
                'checkpoint_time' => Carbon::now(),
                'raw_payload' => $responseDTO->raw_payload,
                'provider' => $providerCode,
                'description' => $responseDTO->status === 'creating' ? 'Label creation initiated asynchronously.' : 'Label created successfully.',
            ]);

            // If the label is being created asynchronously, dispatch a polling job
            if ($responseDTO->status === 'creating') {
                PollAfterShipLabelJob::dispatch($shipment, $responseDTO->waybill_number)
                    ->delay(now()->addSeconds(30));

                Log::info("DispatchShipmentCreationJob: Dispatched PollAfterShipLabelJob for Shipment {$shipment->id} (waybill: {$responseDTO->waybill_number})");
            }

            // Status History & Order Update
            $oldStatus = $this->order->order_status;
            $newStatus = OrderStatus::Shipped->value;

            $this->order->statusHistory()->create([
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'source' => 'system',
                'shipping_partner_id' => $partner->id ?? null,
                'shipment_id' => $shipment->id,
                'admin_id' => $this->adminId,
            ]);

            $this->order->update(['order_status' => $newStatus]);

            DB::commit();

            // Fire Events
            event(new OrderShipped($this->order));
            if ($oldStatus !== $newStatus) {
                event(new OrderStatusUpdated($this->order, $oldStatus, $newStatus));
            }

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Shipment Creation Failed for Order {$this->order->id}: ".$e->getMessage());

            $fullPayload = null;
            if ($e instanceof ShippingProviderException) {
                $fullPayload = $e->getPayload();
            }

            try {
                $this->order->statusHistory()->create([
                    'from_status' => $this->order->order_status,
                    'to_status' => OrderStatus::Failed->value,
                    'reason' => 'Shipment Creation Error: '.substr($e->getMessage(), 0, 150),
                    'source' => 'system',
                    'admin_id' => $this->adminId,
                    'full_payload' => $fullPayload,
                ]);
            } catch (Exception $logException) {
                Log::error("Failed to write to status history for Order {$this->order->id}: ".$logException->getMessage());
            }

            throw $e;
        } finally {
            // Always release the distributed lock, regardless of outcome.
            $lock->forceRelease();
        }
    }

    /**
     * Returns a stable, per-attempt job identifier usable as an idempotency key seed.
     * Falls back to a UUID when the queue job ID is unavailable (sync driver, tests).
     */
    private function jobId(): string
    {
        return $this->job?->getJobId() ?? Str::uuid()->toString();
    }

    /**
     * Generate sequential shipment number (Global)
     */
    protected function generateShipmentNumber(): string
    {
        $prefix = 'SHP';

        // Ensure the global sequence exists (without lock first)
        ShipmentSequence::firstOrCreate(
            ['prefix' => $prefix],
            ['current_value' => 0]
        );

        $sequence = ShipmentSequence::where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        $sequence->current_value++;

        $shipmentNumber = $sequence->prefix.'-'.str_pad($sequence->current_value, 7, '0', STR_PAD_LEFT);

        $sequence->last_shipment_number = $shipmentNumber;
        $sequence->save();

        return $shipmentNumber;
    }
}
