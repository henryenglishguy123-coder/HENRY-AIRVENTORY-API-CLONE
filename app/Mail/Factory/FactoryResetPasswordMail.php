<?php

namespace App\Mail\Factory;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FactoryResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $factory;

    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct($factory, $url)
    {
        $this->factory = $factory;
        $this->url = $url;
    }

    /**
     * Email envelope (subject + from address).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@airventory.io', 'Airventory'),
            subject: __('Reset Password Notification'),
        );
    }

    /**
     * Email content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.factory.reset_password',
            with: [
                'name' => $this->factory->first_name,
                'url' => $this->url,
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
