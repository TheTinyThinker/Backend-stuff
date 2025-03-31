<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $leaderboard;
    public $previousQuestionAnswers;
    public $previousQuestionResults;

    /**
     * Create a new event instance.
     */
    public function __construct($roomCode, $leaderboard, $previousQuestionAnswers=null, $previousQuestionResults=null)
    //here now i realise i also need to add rusults here if i want to have the last page workin
    {
        $this->roomCode = $roomCode;
        $this->leaderboard = $leaderboard;
        $this->previousQuestionAnswers = $previousQuestionAnswers;
        $this->previousQuestionResults = $previousQuestionResults;
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
        return "QuizEnded";
    }

    public function broadcastWith()
    {
        return [
            'leaderboard' => $this->leaderboard,
            'previousQuestionAnswers' => $this->previousQuestionAnswers,
            'previousQuestionResults' => $this->previousQuestionResults,
        ];
    }
}
