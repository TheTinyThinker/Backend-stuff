<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizSelected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $quiz;

    public function __construct($roomCode, $quiz)
    {
        $this->roomCode = $roomCode;
        $this->quiz = $quiz;
    }

    public function broadcastOn()
    {
        return new Channel("quiz-game.{$this->roomCode}");
    }

    
    public function broadcastAs()
    {
        return "QuizSelected";
    }

    public function broadcastWith()
    {
        return [
            'quiz' => $this->quiz,
        ];
    }

}
