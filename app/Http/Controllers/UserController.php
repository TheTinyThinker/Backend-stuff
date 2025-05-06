<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display the specified user profile with stats.
     */
    public function show($id)
    {
        $user = User::with(['answers', 'leaderboards'])->findOrFail($id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture
                    ? url('api/images/' . $user->profile_picture)
                    : null,
                'created_at' => $user->created_at,
            ],
            'stats' => [
                'total_score' => $user->total_score,
                'correct_answers' => $user->correct_answers,
                'incorrect_answers' => $user->incorrect_answers,
                'correct_percentage' => $user->correct_percentage . '%',
                'total_questions_answered' => $user->total_questions_answered,
                'total_quizzes_attempted' => $user->total_quizzes_attempted,
                'highest_score' => $user->highest_score,
                'average_score' => round($user->average_score, 1)
            ]
        ]);
    }

    /**
     * Update the specified user profile.
     */
    public function update(Request $request, $id)
    {
        // Validate input
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'sometimes|required|min:6|confirmed',
            'profile_picture' => 'nullable|string|max:2048',
            'new_profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240'
        ]);

        $user = User::findOrFail($id);

        // Update password if provided
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        try {

            // Handle profile picture upload
            if ($request->hasFile('new_profile_picture')) {
                // Delete old profile picture if it exists
                if ($user->profile_picture) {
                    // Log deletion attempt for debugging
                    \Log::info('Attempting to delete profile picture:', ['path' => $user->profile_picture]);

                    // Try deleting from both potential storage locations
                    if (Storage::disk('db-backend')->exists($user->profile_picture)) {
                        Storage::disk('db-backend')->delete($user->profile_picture);
                        \Log::info('Deleted profile picture from db-backend disk');
                    } elseif (Storage::disk('public')->exists($user->profile_picture)) {
                        Storage::disk('public')->delete($user->profile_picture);
                        \Log::info('Deleted profile picture from public disk');
                    } else {
                        // Path might include 'public/' prefix
                        $trimmedPath = str_replace('public/', '', $user->profile_picture);
                        if (Storage::disk('public')->exists($trimmedPath)) {
                            Storage::disk('public')->delete($trimmedPath);
                            \Log::info('Deleted profile picture with trimmed path', ['path' => $trimmedPath]);
                        } else {
                            \Log::warning('Could not find profile picture to delete', [
                                'original_path' => $user->profile_picture,
                                'trimmed_path' => $trimmedPath
                            ]);
                        }
                    }
                }

                // Store new profile picture using the same disk as your other images
                $filePath = $request->file('new_profile_picture')->store('profile_pictures', 'db-backend');
                $validatedData['profile_picture'] = $filePath;
                \Log::info('Stored new profile picture', ['path' => $filePath]);
            }

            $user->update($validatedData);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user,
                'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }



    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        // Check if the authenticated user is deleting their own account
        if (Auth::id() != $id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
