<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this;

        $isFactory = $request->user('factory') !== null;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'shipping_method' => $order->shipping_method,
            'shipping_address' => OrderAddressResource::make($order->whenLoaded('shippingAddress')),
            'billing_address' => OrderAddressResource::make($order->whenLoaded('billingAddress')),
            'payments' => $this->when(! $isFactory, fn () => $order->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'payment_method' => $payment->payment_method,
                'gateway' => $payment->gateway,
                'status' => $payment->payment_status,
                'amount' => format_price($payment->amount),
                'currency' => $payment->currency_code,
                'paid_at' => $payment->paid_at,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at,
            ])),
            'breakdown' => [
                'subtotal' => format_price($isFactory ? $order->base_subtotal_before_discount : $order->base_subtotal_inc_margin_before_discount),
                'discount' => format_price($isFactory ? $order->base_discount : $order->base_discount_inc_margin),
                'shipping' => format_price($order->shipping_total),
                'tax' => format_price($isFactory ? $order->grand_subtotal_tax : $order->grand_subtotal_tax_inc_margin),
                'total' => format_price($isFactory ? $order->grand_total : $order->grand_total_inc_margin),
            ],
            'items' => OrderItemResource::collection($order->items),
            'customer' => $this->when($request->user('admin_api'), fn () => [
                'id' => $this->customer_id,
                'name' => $this->customer ? trim($this->customer->first_name.' '.$this->customer->last_name) : 'N/A',
                'email' => $this->customer?->email,
            ]),
            'factory' => $this->when($request->user('admin_api'), fn () => $this->factory ? [
                'id' => $this->factory->id,
                'name' => $this->factory->business && $this->factory->business->company_name
                    ? $this->factory->business->company_name
                    : trim($this->factory->first_name.' '.$this->factory->last_name),
            ] : null),
            'source' => $this->when(! $isFactory, fn () => $this->whenLoaded(
                'sourceInfo',
                fn () => SalesOrderSourceResource::make($order->sourceInfo),
                SalesOrderSourceResource::default()
            )),
            'created_at' => $order->created_at,
            'shipments' => OrderShipmentResource::collection($this->whenLoaded('shipments')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
        ];
    }
}
