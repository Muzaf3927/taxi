<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverTripCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $trip;

    public function __construct(array $trip)
    {
        $this->trip = $trip;
    }

    public function broadcastOn(): array
    {
        return [new Channel('driver-trips')];
    }

    public function broadcastAs(): string
    {
        return 'trip.created';
    }
}
