<?php

namespace App\Mail\Customer;

use App\Models\Customer\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CustomerPasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Vendor $customer;

    public $ip;

    public Carbon $changedAt;

    public string $resetUrl;

    public function __construct(Vendor $customer, $ip = null)
    {
        $this->customer = $customer;
        $this->ip = $ip;
        $this->changedAt = now();
        $panelUrl = rtrim(config('app.customer_panel_url'), '/');
        $this->resetUrl = $panelUrl.'/forgot-password';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Password Was Updated'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer.password_changed',
            with: [
                'name' => $this->customer->first_name,
                'ip' => $this->ip,
                'changedAt' => $this->changedAt,
                'resetUrl' => $this->resetUrl,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
