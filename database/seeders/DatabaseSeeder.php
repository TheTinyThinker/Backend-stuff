<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Leaderboard;
use App\Models\Friendship;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();

        try {
            // Create users
            $users = User::factory(10)->create();

            // Ensure at least two users exist before creating friendships
            if ($users->count() > 1) {
                foreach ($users as $user) {
                    $friend = $users->where('id', '!=', $user->id)->random();
                    Friendship::create([
                        'user_id' => $user->id,
                        'friend_id' => $friend->id,
                        'status' => 'pending',
                    ]);
                }
            }

            // Categories for quizzes
            $categories = ['Science', 'History', 'Geography', 'Entertainment', 'Sports', 'Technology', 'General Knowledge'];

            // Create quizzes
            $quizzes = Quiz::factory(5)->create()->each(function ($quiz) use ($users, $categories) {
                $quiz->update([
                    'user_id' => $users->random()->id,
                    'category' => $categories[array_rand($categories)],
                    'img_url' => 'https://picsum.photos/id/' . rand(1, 100) . '/200/200',
                    'show_correct_answer' => (bool) rand(0, 1),
                    'is_public' => (bool) rand(0, 5) > 0, // 5/6 chance of being public
                ]);
            });

            // Generate questions for quizzes
            foreach ($quizzes as $quiz) {
                $questions = Question::factory(5)->create([
                    'quiz_id' => $quiz->id,
                    'question_type' => rand(0, 1) ? 'single choice' : 'multiple choice',
                    'difficulty' => ['easy', 'medium', 'hard'][array_rand(['easy', 'medium', 'hard'])],
                    'img_url' => rand(0, 3) ? null : 'https://picsum.photos/id/' . rand(100, 200) . '/200/200',
                    'time_to_answer' => [10, 15, 20, 30, 45, 60][array_rand([10, 15, 20, 30, 45, 60])],
                ]);

                foreach ($questions as $question) {
                    $numIncorrect = rand(2, 3);
                    $numCorrect = $question->question_type === 'single choice' ? 1 : rand(1, 2);

                    // Create incorrect answers
                    Answer::factory($numIncorrect)->create([
                        'question_id' => $question->id,
                        'is_correct' => false,
                        'user_id' => $users->random()->id, // Assign a random user
                    ]);

                    // Create correct answers
                    Answer::factory($numCorrect)->create([
                        'question_id' => $question->id,
                        'is_correct' => true,
                        'user_id' => $users->random()->id, // Assign a random user
                    ]);
                }
            }

            // Create leaderboard records
            Leaderboard::factory(10)->create([
                'user_id' => fn() => $users->random()->id,
                'quiz_id' => fn() => Quiz::all()->random()->id,
                'points' => rand(0, 100),
            ]);

            // Calculate and update user statistics based on generated data
            foreach ($users as $user) {
                // Get correct and incorrect answers
                $correctAnswers = Answer::where('user_id', $user->id)
                                        ->where('is_correct', true)
                                        ->count();

                $incorrectAnswers = Answer::where('user_id', $user->id)
                                          ->where('is_correct', false)
                                          ->count();

                $totalAnswers = $correctAnswers + $incorrectAnswers;

                // Calculate correct percentage
                $correctPercentage = $totalAnswers > 0
                                     ? round(($correctAnswers / $totalAnswers) * 100, 1)
                                     : 0;

                // Get quiz stats
                $quizzesAttempted = Leaderboard::where('user_id', $user->id)
                                              ->distinct('quiz_id')
                                              ->count();

                $highestScore = Leaderboard::where('user_id', $user->id)
                                          ->max('points') ?? 0;

                $averageScore = Leaderboard::where('user_id', $user->id)
                                          ->avg('points') ?? 0;

                // Update user with calculated stats
                $user->update([
                    'total_score' => Leaderboard::where('user_id', $user->id)->sum('points'),
                    'correct_answers' => $correctAnswers,
                    'incorrect_answers' => $incorrectAnswers,
                    'correct_percentage' => $correctPercentage,
                    'total_questions_answered' => $totalAnswers,
                    'total_quizzes_attempted' => $quizzesAttempted,
                    'highest_score' => $highestScore,
                    'average_score' => $averageScore,
                ]);

                // Generate some simulated game history for each user
                foreach (range(1, rand(3, 8)) as $i) {
                    $quiz = $quizzes->random();
                    $score = rand(0, 100);

                    Leaderboard::create([
                        'user_id' => $user->id,
                        'quiz_id' => $quiz->id,
                        'points' => $score,
                        'created_at' => now()->subDays(rand(1, 30)) // Random date in the last month
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Seeder failed: " . $e->getMessage());
        }
    }
}
