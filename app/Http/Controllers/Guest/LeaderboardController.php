<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Display global leaderboard of top players
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $globalLeaders = User::select('id', 'name', 'highest_score', 'total_score', 'total_quizzes_attempted')
            ->where('total_quizzes_attempted', '>', 0)
            ->orderBy('highest_score', 'desc')
            ->take(20)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $user->id,
                    'name' => $user->name,
                    'highest_score' => $user->highest_score,
                    'total_score' => $user->total_score,
                    'quizzes_played' => $user->total_quizzes_attempted,
                    'average_score' => $user->total_quizzes_attempted > 0
                        ? round($user->total_score / $user->total_quizzes_attempted)
                        : 0
                ];
            });

        return response()->json($globalLeaders);
    }

    /**
     * Display quiz specific leaderboard
     *
     * @param int $quizId
     * @return \Illuminate\Http\JsonResponse
     */
    public function quizLeaderboard($quizId)
    {
        // Check if quiz exists and is public
        $quiz = Quiz::where('id', $quizId)
            ->where('is_public', true)
            ->firstOrFail();

        // Get top scores for this quiz from leaderboard entries
        $topScores = DB::table('leaderboard_entries')
            ->select('leaderboard_entries.score', 'users.id', 'users.name')
            ->join('users', 'leaderboard_entries.user_id', '=', 'users.id')
            ->where('leaderboard_entries.quiz_id', $quizId)
            ->orderBy('leaderboard_entries.score', 'desc')
            ->take(10)
            ->get()
            ->map(function ($entry, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'score' => $entry->score
                ];
            });

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title
            ],
            'leaderboard' => $topScores
        ]);
    }

    /**
     * Display recent high scores across all quizzes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recentScores()
    {
        $recentScores = DB::table('leaderboard_entries')
            ->select(
                'leaderboard_entries.score',
                'leaderboard_entries.created_at',
                'users.id as user_id',
                'users.name as user_name',
                'quizzes.id as quiz_id',
                'quizzes.title as quiz_title'
            )
            ->join('users', 'leaderboard_entries.user_id', '=', 'users.id')
            ->join('quizzes', 'leaderboard_entries.quiz_id', '=', 'quizzes.id')
            ->where('quizzes.is_public', true)
            ->orderBy('leaderboard_entries.created_at', 'desc')
            ->take(15)
            ->get()
            ->map(function ($entry) {
                return [
                    'user' => [
                        'id' => $entry->user_id,
                        'name' => $entry->user_name
                    ],
                    'quiz' => [
                        'id' => $entry->quiz_id,
                        'title' => $entry->quiz_title
                    ],
                    'score' => $entry->score,
                    'date' => $entry->created_at
                ];
            });

        return response()->json($recentScores);
    }
}
