<?php

namespace App\Http\Controllers\Admin\Order;

use App\Actions\Shipping\ShipFullOrderAction;
use App\Enums\Order\OrderStatus;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Shipping\ShipmentCancelled;
use App\Http\Controllers\Controller;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\Services\Shipping\ShippingProviderManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderShipmentController extends Controller
{
    /**
     * Ship full order (Admin Ship Now Flow)
     */
    public function ship(SalesOrder $order): JsonResponse
    {
        try {
            ShipFullOrderAction::run($order);

            return response()->json([
                'success' => true,
                'message' => __('Shipment creation job dispatched successfully.'),
            ], 200);
        } catch (Exception $e) {
            Log::error("Failed to execute ShipFullOrderAction for Order {$order->id}: ".$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to initialize shipment flow.'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel an existing shipment.
     */
    public function cancel(SalesOrderShipment $shipment): JsonResponse
    {
        try {
            DB::beginTransaction();

            $order = $shipment->order;

            if ($shipment->status === ShipmentStatusEnum::CANCELLED->value) {
                return response()->json([
                    'success' => false,
                    'message' => __('This shipment is already cancelled.'),
                ], 422);
            }

            $initialLog = $shipment->trackingLogs()->first();
            $providerCode = $initialLog?->provider ?? 'aftership';

            $partner = $order->factory?->shippingPartners()->where('code', $providerCode)->first()
                ?? \App\Models\Shipping\ShippingPartner::where('code', $providerCode)->first();

            if (! $partner) {
                throw new Exception("Shipping partner config not found for {$providerCode}");
            }

            // Require a valid authenticated admin — no hardcoded fallback
            $adminId = Auth::guard('admin_api')->id();
            if (! $adminId) {
                throw new Exception('No authenticated admin found. Cancellation must be performed by an authenticated admin.');
            }

            // Resolve Provider & Cancel Shipment
            $provider = ShippingProviderManager::resolve($partner);
            $provider->cancelShipment($shipment);
            // Log Status History (Shipment Cancelled)
            $oldStatus = $order->order_status;

            // Revert back to proper status based on history
            $previousHistory = $order->statusHistory()
                ->where('shipment_id', $shipment->id)
                ->where('to_status', OrderStatus::Shipped->value)
                ->latest()
                ->first();

            $newStatus = $previousHistory ? $previousHistory->from_status : OrderStatus::Confirmed->value;

            $order->statusHistory()->create([
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'source' => 'system',
                'shipment_id' => $shipment->id,
                'shipping_partner_id' => $partner->id,
                'reason' => 'Admin cancelled the shipment manually.',
                'admin_id' => $adminId,
            ]);

            $order->update(['order_status' => $newStatus]);

            // Update shipment record status
            $shipment->update(['status' => ShipmentStatusEnum::CANCELLED->value]);

            // Add a cancellation tracking log
            $shipment->trackingLogs()->create([
                'status' => ShipmentStatusEnum::CANCELLED->value,
                'checkpoint_time' => now(),
                'provider' => $providerCode,
                'description' => 'Shipment cancelled by admin.',
            ]);

            DB::commit();

            // Fire Events
            event(new ShipmentCancelled($shipment));
            if ($oldStatus !== $newStatus) {
                event(new OrderStatusUpdated($order, $oldStatus, $newStatus));
            }

            return response()->json([
                'success' => true,
                'message' => __('Shipment cancelled successfully.'),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to cancel shipment {$shipment->id}: ".$e->getMessage());

            $fullPayload = null;
            if ($e instanceof \App\Exceptions\Shipping\ShippingProviderException) {
                $fullPayload = $e->getPayload();
            }

            // Also log the failure in history if it's a provider error
            $order->statusHistory()->create([
                'from_status' => $order->order_status,
                'to_status' => OrderStatus::Failed->value,
                'source' => 'system',
                'shipment_id' => $shipment->id,
                'shipping_partner_id' => $partner->id ?? null,
                'reason' => 'Shipment Cancellation Failed: '.$e->getMessage(),
                'full_payload' => $fullPayload,
                'admin_id' => Auth::guard('admin_api')->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Unable to cancel shipment.'),
                'error' => $e->getMessage(),
                'full_payload' => $fullPayload,
            ], 400); // 400 is better for handled carrier errors
        }
    }
}
