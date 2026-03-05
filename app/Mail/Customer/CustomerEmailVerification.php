<?php

namespace App\Mail\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerEmailVerification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $customer;

    public $verifyUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($customer, $verifyUrl)
    {
        $this->customer = $customer;
        $this->verifyUrl = $verifyUrl;
    }

    /**
     * Email envelope (subject + from address).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@airventory.io', 'Airventory'),
            subject: __('Verify Your Airventory Account'),
        );
    }

    /**
     * Email content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer.verify',
            with: [
                'name' => $this->customer->first_name,
                'verifyUrl' => $this->verifyUrl,
            ]
        );
    }

    /**
     * Email attachments.
     */
    public function attachments(): array
    {
        return [];
    }
}
