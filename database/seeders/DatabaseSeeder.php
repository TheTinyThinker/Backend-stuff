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

        // Create 5 quizzes, each assigned to a random user
        if ($users->isNotEmpty()) {
            Quiz::factory(5)->create()->each(function ($quiz) use ($users) {
                $quiz->user_id = $users->random()->id;
                $quiz->save();
            });
        }

        // Generate questions for quizzes
        Quiz::all()->each(function ($quiz) {
            $questions = Question::factory(5)->create(['quiz_id' => $quiz->id]);

            // For each question, create answers
            $questions->each(function ($question) {
                Answer::factory(3)->create([
                    'question_id' => $question->id,
                    'is_correct' => false
                ]);
                Answer::factory()->create([
                    'question_id' => $question->id,
                    'is_correct' => true
                ]);
            });
        });

        // Create leaderboards
        Leaderboard::factory(10)->create();
    }
}
