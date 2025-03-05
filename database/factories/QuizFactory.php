<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'category' => null, // Set in the seeder
            'img_url' => null, // Set in the seeder
            'show_correct_answer' => false, // Set in the seeder
            'is_public' => true, // Default to public, can be overridden in seeder
            'user_id' => User::factory(),
        ];
    }
}
