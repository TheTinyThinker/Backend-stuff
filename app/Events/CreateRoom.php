<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreateRoom implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;

    public function __construct($roomCode)
    {
        $this->roomCode = $roomCode;
    }

    public function broadcastOn()
    {
        return new Channel('quiz-game.' . $this->roomCode);
    }

    public function broadcastAs()
    {
        return ('GameRoomCreated');
    }
}

