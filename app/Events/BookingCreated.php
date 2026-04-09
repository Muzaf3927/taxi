<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $booking;
    public string $recipientType; // 'driver' or 'passenger'
    public int $recipientId;

    public function __construct(array $booking, string $recipientType, int $recipientId)
    {
        $this->booking = $booking;
        $this->recipientType = $recipientType;
        $this->recipientId = $recipientId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("{$this->recipientType}.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'booking.created';
    }
}
