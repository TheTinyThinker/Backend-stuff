<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $isOpen;

    /**
     * Create a new event instance.
     */
    public function __construct($roomCode, $isOpen)
    {
        $this->roomCode = $roomCode;
        $this->isOpen = $isOpen;
    }

    public function broadcastOn()
    {
        return new Channel('quiz-game.' . $this->roomCode);
    }

    public function broadcastAs()
    {
        return "RoomStatusChanged";
    }

    public function broadcastWith()
    {
        return [
            'roomCode' => $this->roomCode,
            'isOpen' => $this->isOpen,
        ];
    }
}
