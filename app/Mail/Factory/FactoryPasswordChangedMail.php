<?php

namespace App\Mail\Factory;

use App\Models\Factory\Factory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class FactoryPasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Factory $factory;

    public $ip;

    public Carbon $changedAt;

    public string $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Factory $factory, $ip = null)
    {
        $this->factory = $factory;
        $this->ip = $ip;
        $this->changedAt = now();
        $panelUrl = rtrim(config('app.factory_panel_url', config('app.url')), '/');
        $this->resetUrl = $panelUrl.'/auth/forgot-password';
    }

    /**
     * Email envelope (subject + from address).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@airventory.io', 'Airventory'),
            subject: __('Your Password Was Updated'),
        );
    }

    /**
     * Email content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.factory.password_changed',
            with: [
                'name' => $this->factory->first_name,
                'ip' => $this->ip,
                'changedAt' => $this->changedAt,
                'resetUrl' => $this->resetUrl,
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
