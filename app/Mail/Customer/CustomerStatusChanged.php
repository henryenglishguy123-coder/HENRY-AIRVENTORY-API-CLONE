<?php

namespace App\Mail\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $customer;

    public $action;

    public $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($customer, string $action, string $loginUrl)
    {
        $this->customer = is_array($customer) ? (object) $customer : $customer;
        $this->action = $action;
        $this->loginUrl = $loginUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubject(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer.customer_status_changed',
            with: [
                'customer' => $this->customer,
                'action' => $this->action,
                'loginUrl' => $this->loginUrl,
            ]
        );
    }

    /**
     * Get the email subject based on the action.
     */
    private function getSubject(): string
    {
        $appName = env('APP_NAME', 'Our Platform');

        return match ($this->action) {
            'enable', 'disable', 'blocked', 'suspended', 'deleted' => __(
                "customer.email_subjects.{$this->action}",
                ['appName' => $appName]
            ),
            default => __('customer.email_subjects.default', ['appName' => $appName]),
        };
    }

    public function attachments(): array
    {
        return [];
    }
}
