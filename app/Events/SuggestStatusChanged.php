<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuggestStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $canSuggest;

    /**
     * Create a new event instance.
     */
    public function __construct($roomCode, $canSuggest)
    {
        $this->roomCode = $roomCode;
        $this->canSuggest = $canSuggest;
    }

    public function broadcastOn()
    {
        return new Channel('quiz-game.' . $this->roomCode);
    }

    public function broadcastAs()
    {
        return "SuggestStatusChanged";
    }

    public function broadcastWith()
    {
        return [
            'roomCode' => $this->roomCode,
            'canSuggest' => $this->canSuggest,
        ];
    }
}
