<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserChange implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $users;

    public function __construct($roomCode, $users)
    {
        $this->roomCode = $roomCode;
        $this->users = $users;
    }

    public function broadcastOn()
    {
        return new Channel('quiz-game.' . $this->roomCode);
    }

    public function broadcastAs()
    {
        return "RoomUpdated";
    }

    public function broadcastWith()
    {
        return [
            'roomCode' => $this->roomCode,
            'users' => $this->users,
        ];
    }
}
