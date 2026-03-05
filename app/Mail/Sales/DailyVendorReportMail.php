<?php

namespace App\Mail\Sales;

use App\Models\Customer\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DailyVendorReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Vendor $vendor;

    public array $metrics;

    public function __construct(Vendor $vendor, array $metrics)
    {
        $this->vendor = $vendor;
        $this->metrics = $metrics;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Daily Sales Report'),
        );
    }

    public function content(): Content
    {
        $base = config('app.customer_panel_url') ?: config('app.url');
        if (! is_string($base) || trim($base) === '') {
            Log::error('DailyVendorReportMail: missing base URL for orders link', [
                'vendor_id' => $this->vendor->id,
            ]);
            $ordersUrl = '#';
        } else {
            $ordersUrl = rtrim($base, '/').'/orders';
        }

        return new Content(
            markdown: 'emails.sales.daily_vendor_report',
            with: [
                'vendor' => $this->vendor,
                'metrics' => $this->metrics,
                'ordersUrl' => $ordersUrl,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
