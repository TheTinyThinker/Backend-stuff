<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizSuggested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $suggestedQuizzes;

    public function __construct($roomCode, $suggestedQuizzes)
    {
        $this->roomCode = $roomCode;
        $this->suggestedQuizzes = $suggestedQuizzes;
    }

    public function broadcastOn()
    {
        return new Channel("quiz-game.{$this->roomCode}");
    }

    public function broadcastAs()
    {
        return "QuizSuggested";
    }

    public function broadcastWith()
    {
        return [
            'suggestedQuizzes' => $this->suggestedQuizzes,
        ];
    }

}
