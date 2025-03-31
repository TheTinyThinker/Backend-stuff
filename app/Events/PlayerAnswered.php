<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerAnswered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $userId;
    public $playerCount;

    /**
     * Create a new event instance.
     */
    public function __construct($roomCode, $userId, $playerCount)
    {
        $this->roomCode = $roomCode;
        $this->userId = $userId;
        $this->playerCount = $playerCount;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('quiz-game.' . $this->roomCode);
    }

    public function broadcastAs()
    {
        return "PlayerAnswered";
    }

    public function broadcastWith()
    {
        return [
            'roomCode' => $this->roomCode,
            'userId' => $this->userId,
            'playerCount' => $this->playerCount
        ];
    }
}
