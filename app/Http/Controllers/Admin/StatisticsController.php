<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    /**
     * Display system statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Date range filter
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        // User growth over time
        $userGrowth = User::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Quiz creation stats
        $quizCreation = Quiz::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Category distribution
        $categoryDistribution = Quiz::select(
                'category',
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get();

        // User activity stats
        $userActivity = User::select(
                DB::raw('SUM(total_quizzes_attempted) as total_attempts'),
                DB::raw('AVG(average_score) as average_score'),
                DB::raw('MAX(highest_score) as highest_score')
            )
            ->first();

        // Return all stats
        return response()->json([
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_users' => User::count(),
                'total_quizzes' => Quiz::count(),
                'total_questions' => Question::count(),
                'public_quizzes' => Quiz::where('is_public', true)->count(),
                'private_quizzes' => Quiz::where('is_public', false)->count(),
            ],
            'user_growth' => $userGrowth,
            'quiz_creation' => $quizCreation,
            'category_distribution' => $categoryDistribution,
            'user_activity' => $userActivity,
        ]);
    }
}
