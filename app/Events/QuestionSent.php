<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomCode;
    public $question;
    public $questionNumber;
    public $totalQuestions;
    public $previousQuestionAnswers;
    public $previousQuestionResults;
    public $leaderboard;

    /**
     * Create a new event instance.
     */
    public function __construct($roomCode, $question, $questionNumber, $totalQuestions, $previousQuestionAnswers=null, $previousQuestionResults=null, $leaderboard=null)
    {
        $this->roomCode = $roomCode;
        $this->question = $question;
        $this->questionNumber = $questionNumber;
        $this->totalQuestions = $totalQuestions;
        $this->previousQuestionAnswers = $previousQuestionAnswers;
        $this->previousQuestionResults = $previousQuestionResults;
        $this->leaderboard = $leaderboard;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('quiz-game.' . $this->roomCode); //here the channels are named quiz-game $variale

    }

    public function broadcastAs() //here the event name im listening for
    {
        return "NextQuestion";
    }

    public function broadcastWith()  //here the data + im addin question result here bc i kinda setup frontend like this already and i think changing this is faster
    {
        return [
            'message' => 'Quiz started',
            'currentQuestion' => $this->question,
            'questionNumber' => $this->questionNumber,
            'totalQuestions' => $this->totalQuestions,
            'previousQuestionAnswers' => $this->previousQuestionAnswers,
            'previousQuestionResults' => $this->previousQuestionResults,
            'liveLeaderboard' => $this->leaderboard,
        ];
    }
}
