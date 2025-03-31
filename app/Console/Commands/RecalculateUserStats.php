<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Answer;
use App\Models\Leaderboard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateUserStats extends Command
{
    protected $signature = 'stats:recalculate {--user=} {--all}';
    protected $description = 'Recalculate user statistics';

    public function handle()
    {
        $userId = $this->option('user');

        if ($userId) {
            $this->recalculateForUser(User::find($userId));
            $this->info("Stats recalculated for user #{$userId}");
        } elseif ($this->option('all')) {
            User::chunk(100, function ($users) {
                foreach ($users as $user) {
                    $this->recalculateForUser($user);
                }
            });
            $this->info("Stats recalculated for all users");
        } else {
            $this->error("Please specify --user=ID or --all");
        }
    }

    protected function recalculateForUser(User $user)
    {
        // Count answers
        $correctAnswers = Answer::where('user_id', $user->id)
                                ->where('is_correct', true)->count();
        $incorrectAnswers = Answer::where('user_id', $user->id)
                                  ->where('is_correct', false)->count();
        $totalAnswers = $correctAnswers + $incorrectAnswers;

        // Get quiz stats
        $totalScore = Leaderboard::where('user_id', $user->id)->sum('points');
        $quizzesAttempted = Leaderboard::where('user_id', $user->id)
                                     ->distinct('quiz_id')->count();
        $highestScore = Leaderboard::where('user_id', $user->id)->max('points') ?? 0;
        $averageScore = Leaderboard::where('user_id', $user->id)->avg('points') ?? 0;

        // Update user
        $user->update([
            'total_score' => $totalScore,
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $incorrectAnswers,
            'correct_percentage' => $totalAnswers > 0 ? ($correctAnswers / $totalAnswers) * 100 : 0,
            'total_questions_answered' => $totalAnswers,
            'total_quizzes_attempted' => $quizzesAttempted,
            'highest_score' => $highestScore,
            'average_score' => $averageScore
        ]);
    }
}
