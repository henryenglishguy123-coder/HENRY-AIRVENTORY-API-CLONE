<?php

namespace App\Events\Factory;

use App\Models\Factory\Factory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FactoryStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $factory;

    public $changes;

    public $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Factory $factory, array $changes, ?string $reason = null)
    {
        $this->factory = $factory;
        $this->changes = $changes;
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
