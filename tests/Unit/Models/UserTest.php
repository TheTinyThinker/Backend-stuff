<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'total_score' => 450,
            'correct_answers' => 45,
            'incorrect_answers' => 15,
            'correct_percentage' => 75,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'total_score' => 450,
                'correct_answers' => 45,
                'incorrect_answers' => 15,
                'correct_percentage' => 75,
            ]);
    }

    public function test_user_can_update_profile()
    {
        Storage::fake('db-backend');

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully',
                'user' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        // Check that profile picture was stored
        $updatedUser = User::find($user->id);
        $this->assertNotNull($updatedUser->profile_picture);
        Storage::disk('db-backend')->assertExists($updatedUser->profile_picture);
    }
}
