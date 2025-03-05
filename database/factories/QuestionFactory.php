<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition()
    {
        return [
            'quiz_id' => Quiz::factory(),
            'question_text' => $this->faker->sentence(10) . '?',
            'question_type' => 'single choice', // Will be overridden in seeder
            'difficulty' => 'medium', // Will be overridden in seeder
            'img_url' => null, // Will be overridden in seeder
            'time_to_answer' => 30, // Will be overridden in seeder
        ];
    }
}
