<?php

namespace App\Mail\Factory;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FactoryGoogleLoginPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $factory;

    public $url;

    public function __construct($factory, string $url)
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
            subject: __('Set your :app password', ['app' => config('app.name', 'Airventory')]),
        );
    }

    /**
     * Email content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.factory.google_login_password',
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
