<?php

namespace Database\Factories;

use App\Models\Leaderboard;
use App\Models\User;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaderboardFactory extends Factory
{
    protected $model = Leaderboard::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'quiz_id' => Quiz::factory(),
            'points' => $this->faker->numberBetween(0, 100),
        ];
    }
}
