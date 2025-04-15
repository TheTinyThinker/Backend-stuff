<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display admin dashboard data.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get user statistics
        $userStats = [
            'total' => User::count(),
            'new_today' => User::whereDate('created_at', today())->count(),
            'active_this_week' => User::where('updated_at', '>=', now()->subDays(7))->count(),
        ];

        // Get quiz statistics
        $quizStats = [
            'total' => Quiz::count(),
            'new_today' => Quiz::whereDate('created_at', today())->count(),
            'public' => Quiz::where('is_public', true)->count(),
            'private' => Quiz::where('is_public', false)->count(),
        ];

        // Get most active users
        $mostActiveUsers = User::select('id', 'name', 'email', 'total_quizzes_attempted')
            ->orderBy('total_quizzes_attempted', 'desc')
            ->limit(5)
            ->get();

        // Get most popular quizzes
        $popularQuizzes = Quiz::select('id', 'title', 'user_id', 'rating_count')
            ->with('user:id,name')
            ->orderBy('rating_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'user_stats' => $userStats,
            'quiz_stats' => $quizStats,
            'active_users' => $mostActiveUsers,
            'popular_quizzes' => $popularQuizzes
        ]);
    }
}
