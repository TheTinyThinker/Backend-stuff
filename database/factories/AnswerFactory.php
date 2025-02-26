<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    public function definition()
    {
        return [
            'question_id' => Question::factory(),
            'answer_text' => $this->faker->sentence(3),
            'is_correct' => $this->faker->boolean(25), // 25% chance of being correct
        ];
    }
}
