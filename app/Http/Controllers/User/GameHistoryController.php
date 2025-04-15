<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GameHistoryController extends Controller
{
     /**
     * Display a listing of the user's game history.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Get all game records for the user
        $gameHistory = DB::table('leaderboard_entries')
            ->select(
                'leaderboard_entries.id',
                'leaderboard_entries.quiz_id',
                'leaderboard_entries.score',
                'leaderboard_entries.created_at',
                'quizzes.title as quiz_title',
                'quizzes.img_url',
                'quizzes.category'
            )
            ->join('quizzes', 'leaderboard_entries.quiz_id', '=', 'quizzes.id')
            ->where('leaderboard_entries.user_id', $userId)
            ->orderBy('leaderboard_entries.created_at', 'desc')
            ->get();

        return response()->json($gameHistory);
    }

    /**
     * Display detailed statistics about the user's game performance.
     *
     * @return \Illuminate\Http\Response
     */
    public function statistics()
    {
        $userId = Auth::id();
        $user = User::findOrFail($userId);

        // Basic stats
        $basicStats = [
            'total_quizzes_attempted' => $user->total_quizzes_attempted,
            'highest_score' => $user->highest_score,
            'average_score' => $user->average_score,
            'total_score' => $user->total_score,
            'correct_answers' => $user->correct_answers,
            'incorrect_answers' => $user->incorrect_answers,
        ];

        // Calculate accuracy if there are answers
        if ($user->correct_answers + $user->incorrect_answers > 0) {
            $basicStats['accuracy'] = round(($user->correct_answers / ($user->correct_answers + $user->incorrect_answers)) * 100, 1);
        } else {
            $basicStats['accuracy'] = 0;
        }

        // Performance by category
        $categoryPerformance = DB::table('leaderboard_entries')
            ->select(
                'quizzes.category',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('AVG(leaderboard_entries.score) as average_score'),
                DB::raw('MAX(leaderboard_entries.score) as highest_score')
            )
            ->join('quizzes', 'leaderboard_entries.quiz_id', '=', 'quizzes.id')
            ->where('leaderboard_entries.user_id', $userId)
            ->whereNotNull('quizzes.category')
            ->groupBy('quizzes.category')
            ->get();

        // Recent progress (last 10 games)
        $recentScores = DB::table('leaderboard_entries')
            ->select('leaderboard_entries.score', 'leaderboard_entries.created_at', 'quizzes.title')
            ->join('quizzes', 'leaderboard_entries.quiz_id', '=', 'quizzes.id')
            ->where('leaderboard_entries.user_id', $userId)
            ->orderBy('leaderboard_entries.created_at', 'desc')
            ->limit(10)
            ->get();

        // Most played quizzes
        $mostPlayedQuizzes = DB::table('leaderboard_entries')
            ->select(
                'quizzes.id',
                'quizzes.title',
                DB::raw('COUNT(*) as play_count'),
                DB::raw('MAX(leaderboard_entries.score) as best_score')
            )
            ->join('quizzes', 'leaderboard_entries.quiz_id', '=', 'quizzes.id')
            ->where('leaderboard_entries.user_id', $userId)
            ->groupBy('quizzes.id', 'quizzes.title')
            ->orderBy('play_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'basic_stats' => $basicStats,
            'category_performance' => $categoryPerformance,
            'recent_scores' => $recentScores,
            'most_played' => $mostPlayedQuizzes
        ]);
    }

    /**
     * Display the user's performance for a specific quiz.
     *
     * @param int $quizId
     * @return \Illuminate\Http\Response
     */
    public function quizPerformance($quizId)
    {
        $userId = Auth::id();

        // Verify quiz exists
        $quiz = Quiz::findOrFail($quizId);

        // Get all attempts for this quiz
        $attempts = DB::table('leaderboard_entries')
            ->select('id', 'score', 'created_at')
            ->where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        $stats = [
            'quiz_id' => $quizId,
            'quiz_title' => $quiz->title,
            'attempts_count' => $attempts->count(),
            'highest_score' => $attempts->max('score') ?? 0,
            'average_score' => $attempts->avg('score') ?? 0,
            'first_attempt' => $attempts->last()->created_at ?? null,
            'latest_attempt' => $attempts->first()->created_at ?? null,
        ];

        return response()->json([
            'stats' => $stats,
            'attempts' => $attempts
        ]);
    }
}
