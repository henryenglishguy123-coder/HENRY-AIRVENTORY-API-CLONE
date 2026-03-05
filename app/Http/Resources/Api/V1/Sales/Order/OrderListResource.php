<?php

namespace App\Http\Resources\Api\V1\Sales\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shippingAddress = $this->addresses->where('address_type', 'shipping')->first();
        $recipientName = $shippingAddress
            ? trim($shippingAddress->first_name.' '.$shippingAddress->last_name)
            : null;
        $recipientName = $recipientName === '' ? null : $recipientName;
        $recipientEmail = $shippingAddress?->email ?? $this->customer?->email;
        $recipientPhone = $shippingAddress?->phone;

        $isFactory = $request->user('factory') !== null;
        $priceAmount = $isFactory ? $this->grand_total : $this->grand_total_inc_margin;

        $baseData = [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'recipient_name' => $recipientName,
            'created_at' => $this->created_at,
            'price' => $priceAmount !== null ? format_price($priceAmount) : null,
            'order_status' => $this->order_status,
            $this->mergeWhen(! $isFactory, [
                'payment_status' => $this->payment_status,
            ]),
            'customer' => $this->when($request->user('admin_api'), fn () => [
                'id' => $this->customer_id,
                'name' => $recipientName ?? ($this->customer ? trim($this->customer->first_name.' '.$this->customer->last_name) : 'N/A'),
                'email' => $recipientEmail,
                'phone' => $recipientPhone,
            ]),
            'factory' => $this->when($request->user('admin_api'), fn () => $this->factory ? [
                'id' => $this->factory->id,
                'name' => $this->factory->business && $this->factory->business->company_name
                    ? $this->factory->business->company_name
                    : trim($this->factory->first_name.' '.$this->factory->last_name),
            ] : null),
            'source' => $this->when(! $isFactory, fn () => $this->sourceInfo
                ? new SalesOrderSourceResource($this->sourceInfo)
                : SalesOrderSourceResource::default()),
        ];

        if ($isFactory) {
            $baseData['grand_total'] = $this->grand_total;
        } else {
            $baseData['amount'] = $this->grand_total_inc_margin;
        }

        return $baseData;
    }
}
