<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotingMethodChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $votingMethod;

    public function __construct($roomCode, $votingMethod)
    {
        $this->roomCode = $roomCode;
        $this->votingMethod = $votingMethod;
    }

    public function broadcastOn()
    {
        return new Channel('room.' . $this->roomCode);
    }
}
