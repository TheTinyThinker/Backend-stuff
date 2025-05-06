<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuizControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_quiz()
    {
        Storage::fake('db-backend');

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/quizzes', [
            'title' => 'Test Quiz',
            'description' => 'This is a test quiz',
            'category' => 'General Knowledge',
            'is_public' => true,
            'show_correct_answer' => true,
            'image' => UploadedFile::fake()->image('quiz.jpg'),
            'questions' => [
                [
                    'question_text' => 'What is 1+1?',
                    'question_type' => 'single choice',
                    'difficulty' => 'easy',
                    'time_to_answer' => 30,
                    'answer_options' => [
                        [
                            'answer_text' => '2',
                            'is_correct' => true,
                        ],
                        [
                            'answer_text' => '3',
                            'is_correct' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'category',
                'user_id',
                'is_public',
                'show_correct_answer',
                'img_url',
                'questions' => [
                    '*' => [
                        'id',
                        'quiz_id',
                        'question_text',
                        'question_type',
                        'difficulty',
                        'time_to_answer',
                        'img_url',
                        'answers' => [
                            '*' => [
                                'id',
                                'question_id',
                                'answer_text',
                                'is_correct',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('quizzes', [
            'title' => 'Test Quiz',
            'description' => 'This is a test quiz',
            'user_id' => $user->id,
        ]);

        // Check that the file was stored
        $quiz = Quiz::where('title', 'Test Quiz')->first();
        Storage::disk('db-backend')->assertExists($quiz->img_url);
    }

    public function test_user_can_get_quizzes()
    {
        $user = User::factory()->create();
        $quiz = Quiz::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/quizzes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'category',
                        'user_id',
                        'is_public',
                        'show_correct_answer',
                        'img_url',
                        'created_by',
                        'image_url',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    public function test_user_can_get_quiz_details()
    {
        $user = User::factory()->create();
        $quiz = Quiz::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/quizzes/' . $quiz->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'category',
                'user_id',
                'is_public',
                'show_correct_answer',
                'img_url',
                'created_by',
                'image_url',
                'questions' => [
                    '*' => [
                        'id',
                        'quiz_id',
                        'question_text',
                        'question_type',
                        'difficulty',
                        'time_to_answer',
                        'img_url',
                        'answers',
                    ],
                ],
            ]);
    }

    public function test_user_can_update_quiz()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $quiz = Quiz::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson('/api/quizzes/' . $quiz->id, [
            'title' => 'Updated Quiz Title',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'title' => 'Updated Quiz Title',
                'description' => 'Updated description',
            ]);

        $this->assertDatabaseHas('quizzes', [
            'id' => $quiz->id,
            'title' => 'Updated Quiz Title',
            'description' => 'Updated description',
        ]);
    }

    public function test_user_can_delete_quiz()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $quiz = Quiz::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson('/api/quizzes/' . $quiz->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Quiz deleted successfully']);

        $this->assertDatabaseMissing('quizzes', [
            'id' => $quiz->id,
        ]);
    }

    public function test_user_cannot_delete_others_quiz()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user2);
        $quiz = Quiz::factory()->create(['user_id' => $user1->id]);

        $response = $this->deleteJson('/api/quizzes/' . $quiz->id);

        $response->assertStatus(422);

        $this->assertDatabaseHas('quizzes', [
            'id' => $quiz->id,
        ]);
    }
}
