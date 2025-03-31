<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{

    // public function update(Request $request)
    // {
    //     $request->validate([
    //         'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
    //     ]);

    //     $user = Auth::user();

    //     if (!$user) {
    //         return redirect()->route('login')->with('error', 'You must be logged in to update your profile.');
    //     }

    //     try {
    //         if ($request->hasFile('profile_picture')) {
    //             // Delete old profile picture if exists
    //             if ($user->profile_picture) {
    //                 Storage::delete('public/' . $user->profile_picture);
    //             }

    //             // Store new profile picture
    //             $filePath = $request->file('profile_picture')->store('profile_pictures', 'public');
    //             $user->profile_picture = $filePath;
    //         }

    //         $user->save();

    //         return back()->with('success', 'Profile updated successfully!');
    //     } catch (\Exception $e) {
    //         return back()->with('error', 'Something went wrong: ' . $e->getMessage());
    //     }
    // }

}
