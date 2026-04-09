<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $bookingId;
    public string $status;
    public array $data;
    public string $recipientType;
    public int $recipientId;

    public function __construct(int $bookingId, string $status, array $data, string $recipientType, int $recipientId)
    {
        $this->bookingId = $bookingId;
        $this->status = $status;
        $this->data = $data;
        $this->recipientType = $recipientType;
        $this->recipientId = $recipientId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("{$this->recipientType}.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'booking.status_changed';
    }
}
