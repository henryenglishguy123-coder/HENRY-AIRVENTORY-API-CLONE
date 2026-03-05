<?php

namespace App\Listeners\Factory;

use App\Events\Factory\FactoryStatusChanged;
use App\Mail\FactoryStatusUpdatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendFactoryStatusNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(FactoryStatusChanged $event): void
    {
        Mail::to($event->factory->email)->send(new FactoryStatusUpdatedMail(
            $event->factory,
            $event->changes,
            $event->reason
        ));
    }
}
