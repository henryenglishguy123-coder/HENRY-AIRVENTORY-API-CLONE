<?php

namespace App\Mail\Factory;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $factory;

    public $verificationCode;

    /**
     * Create a new message instance.
     */
    public function __construct($factory, $verificationCode)
    {
        $this->factory = $factory;
        $this->verificationCode = $verificationCode;
    }

    /**
     * Email envelope (subject + from address).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@airventory.io', 'Airventory'),
            subject: __('Verify Your Airventory Factory Account'),
        );
    }

    /**
     * Email content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.factory.verification_code',
            with: [
                'name' => $this->factory->first_name,
                'code' => $this->verificationCode,
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
