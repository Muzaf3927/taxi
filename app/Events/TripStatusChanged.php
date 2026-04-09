<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tripId;
    public string $tripType; // 'driver_trip' or 'passenger_trip'
    public string $status;
    public string $recipientType;
    public int $recipientId;

    public function __construct(int $tripId, string $tripType, string $status, string $recipientType, int $recipientId)
    {
        $this->tripId = $tripId;
        $this->tripType = $tripType;
        $this->status = $status;
        $this->recipientType = $recipientType;
        $this->recipientId = $recipientId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("{$this->recipientType}.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'trip.status_changed';
    }
}
