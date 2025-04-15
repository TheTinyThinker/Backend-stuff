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
                'profile_picture' => $user->profile_picture,
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

                if ($user->profile_picture) {
                    Storage::disk('public')->delete($user->profile_picture);
                }

                $filePath = $request->file('new_profile_picture')->store('profile_pictures', 'public');
                $validatedData['profile_picture'] = $filePath;
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
