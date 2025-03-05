<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Leaderboard;
use App\Models\Friendship;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create 10 users
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

        // Create 5 quizzes, each assigned to a random user
        if ($users->isNotEmpty()) {
            Quiz::factory(5)->create([
                'category' => fn() => $categories[array_rand($categories)],
                'img_url' => fn() => 'https://picsum.photos/id/' . rand(1, 100) . '/200/200',
                'show_correct_answer' => fn() => rand(0, 1) === 1,
                'is_public' => fn() => rand(0, 5) > 0, // 5/6 chance of being public
            ])->each(function ($quiz) use ($users) {
                $quiz->user_id = $users->random()->id;
                $quiz->save();
            });
        }

        // Generate questions for quizzes
        Quiz::all()->each(function ($quiz) {
            // Create 5 questions per quiz
            $questions = Question::factory(5)->create([
                'quiz_id' => $quiz->id,
                'question_type' => fn() => rand(0, 1) === 0 ? 'single choice' : 'multiple choice',
                'difficulty' => fn() => ['easy', 'medium', 'hard'][array_rand(['easy', 'medium', 'hard'])],
                'img_url' => fn() => rand(0, 3) === 0 ? 'https://picsum.photos/id/' . rand(100, 200) . '/200/200' : null,
                'time_to_answer' => fn() => [10, 15, 20, 30, 45, 60][array_rand([10, 15, 20, 30, 45, 60])],
            ]);

            // For each question, create answers
            $questions->each(function ($question) {
                // For single choice questions, only one correct answer
                if ($question->question_type === 'single choice') {
                    // Create 3 incorrect answers
                    Answer::factory(3)->create([
                        'question_id' => $question->id,
                        'is_correct' => false
                    ]);
                    // Create 1 correct answer
                    Answer::factory()->create([
                        'question_id' => $question->id,
                        'is_correct' => true
                    ]);
                }
                // For multiple choice, potentially multiple correct answers
                else {
                    // Create 2-3 incorrect answers
                    $incorrectCount = rand(2, 3);
                    Answer::factory($incorrectCount)->create([
                        'question_id' => $question->id,
                        'is_correct' => false
                    ]);

                    // Create 1-2 correct answers
                    $correctCount = rand(1, 2);
                    Answer::factory($correctCount)->create([
                        'question_id' => $question->id,
                        'is_correct' => true
                    ]);
                }
            });
        });

        // Create leaderboards
        Leaderboard::factory(10)->create([
            'user_id' => fn() => $users->random()->id,
            'quiz_id' => fn() => Quiz::all()->random()->id,
            'points' => fn() => rand(0, 100),
        ]);
    }
}
