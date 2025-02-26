<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Leaderboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Import DB Facade

class AnswerController extends Controller
{
    public function checkAnswer(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id' => 'required|exists:answers,id',
            'user_id' => 'required|exists:users,id',
            'quiz_id' => 'required|exists:quizzes,id',
        ]);

        $answer = Answer::find($request->answer_id);
        $points = $answer->is_correct ? 10 : 0;

        // Update or create the leaderboard entry
        $leaderboard = Leaderboard::updateOrCreate(
            ['user_id' => $request->user_id, 'quiz_id' => $request->quiz_id],
            ['points' => DB::raw("points + $points")] // Use DB::raw properly
        );

        return response()->json([
            'correct' => $answer->is_correct,
            'points' => $leaderboard->points,
        ]);
    }
}
