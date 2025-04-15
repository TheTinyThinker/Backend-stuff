<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * Register a new user with an optional profile picture
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:8|confirmed',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email), // Store email in lowercase
            'password' => Hash::make($request->password),
        ]);

        try {
            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $filePath = $request->file('profile_picture')->store('profile_pictures', 'public');
                $user->profile_picture = $filePath;
                $user->save();
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
                'profile_picture_url' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Log in a user and return an authentication token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Look up user with case-insensitive email
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'profile_picture_url' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null
        ]);
    }

    /**
     * Log out a user by revoking their authentication token
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
