<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     *
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        $user = Auth::user();

        // Get the user's stats
        $stats = [
            'total_quizzes_attempted' => $user->total_quizzes_attempted,
            'highest_score' => $user->highest_score,
            'average_score' => $user->average_score,
            'total_score' => $user->total_score,
            'correct_answers' => $user->correct_answers,
            'incorrect_answers' => $user->incorrect_answers,
        ];

        // Calculate percentages if possible
        if ($user->correct_answers + $user->incorrect_answers > 0) {
            $stats['accuracy'] = round(($user->correct_answers / ($user->correct_answers + $user->incorrect_answers)) * 100, 1);
        } else {
            $stats['accuracy'] = 0;
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'img_url' => $user->img_url,
            'created_at' => $user->created_at,
            'stats' => $stats
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => 'sometimes|required_with:password|string',
            'password' => 'sometimes|required|string|min:8|confirmed',
            'img_url' => 'sometimes|nullable|string|max:2048',
        ]);

        // Verify current password if changing password
        if (isset($validated['password']) && isset($validated['current_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The provided password does not match our records']
                    ]
                ], 422);
            }
        }

        // Update user fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (isset($validated['img_url'])) {
            $user->img_url = $validated['img_url'];
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'img_url' => $user->img_url
            ]
        ]);
    }
}
