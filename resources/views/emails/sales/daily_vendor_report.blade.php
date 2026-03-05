<x-mail::message>

@php
    $vendorName = trim(($vendor->first_name ?? '').' '.($vendor->last_name ?? ''));
    $vendorName = $vendorName ?: __('Vendor');

    $timezone = config('app.timezone');

    $wFrom = $metrics['window']['from'] ?? null;
    $wTo = $metrics['window']['to'] ?? null;

    $totalOrders   = $metrics['window_metrics']['total_orders'] ?? 0;
    $paidOrders    = $metrics['window_metrics']['paid_orders'] ?? 0;
    $unpaidOrders  = $metrics['window_metrics']['unpaid_orders'] ?? 0;
    $shippedOrders = $metrics['window_metrics']['shipped_orders'] ?? 0;
    $exceptions    = $metrics['window_metrics']['exceptions'] ?? 0;

    $overallUnpaid     = $metrics['overall']['unpaid_orders'] ?? 0;
    $overallExceptions = $metrics['overall']['exceptions'] ?? 0;
@endphp

# {{ __('Daily Sales Report') }}

Hello {{ $vendorName }},

Here is your daily order performance summary.

---

## {{ __('Reporting Period') }}

**{{ __('Date') }}:** {{ $wTo ? $wTo->format('d M Y') : '-' }}  
**{{ __('From') }}:** {{ $wFrom ? $wFrom->copy()->timezone($timezone)->format('d M Y, h:i A') : '-' }}  
**{{ __('To') }}:** {{ $wTo ? $wTo->copy()->timezone($timezone)->format('d M Y, h:i A') : '-' }}  
**{{ __('Timezone') }}:** {{ $timezone }}

---

## {{ __('Reporting Period Summary') }}

<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
<tr>
<td><strong>Total Orders</strong></td>
<td align="right"><strong>{{ $totalOrders }}</strong></td>
</tr>
<tr>
<td>Paid Orders</td>
<td align="right">{{ $paidOrders }}</td>
</tr>
<tr>
<td>Unpaid Orders</td>
<td align="right" style="color:#b45309;"><strong>{{ $unpaidOrders }}</strong></td>
</tr>
<tr>
<td>Shipped Orders</td>
<td align="right" style="color:#047857;"><strong>{{ $shippedOrders }}</strong></td>
</tr>
<tr>
<td>Orders with Exceptions</td>
<td align="right" style="color:#b91c1c;"><strong>{{ $exceptions }}</strong></td>
</tr>
</table>

---

## {{ __('Overall Summary') }}

<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
<tr>
<td><strong>Unpaid Orders</strong></td>
<td align="right" style="color:#b45309;"><strong>{{ $overallUnpaid }}</strong></td>
</tr>
<tr>
<td><strong>Orders with Exceptions</strong></td>
<td align="right" style="color:#b91c1c;"><strong>{{ $overallExceptions }}</strong></td>
</tr>
</table>

---

<x-mail::button :url="$ordersUrl">
{{ __('View Orders Dashboard') }}
</x-mail::button>

---

Please review any unpaid or exception orders to ensure smooth fulfillment operations.

Regards,  
{{ config('app.name') }}

</x-mail::message>
