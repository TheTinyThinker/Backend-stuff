<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class FriendshipController extends Controller
{
    /**
     * Display a listing of the user's friends.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userId = Auth::id();

        // Get friendships where user is either the requester or recipient
        $friends = Friendship::where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->where('status', 'accepted');
            })
            ->orWhere(function($query) use ($userId) {
                $query->where('friend_id', $userId)
                      ->where('status', 'accepted');
            })
            ->with(['user:id,name,email,profile_picture', 'friend:id,name,email,profile_picture'])
            ->get()
            ->map(function($friendship) use ($userId) {
                // Determine which user is the friend (not the current user)
                $friend = $friendship->user_id == $userId
                    ? $friendship->friend
                    : $friendship->user;

                return [
                    'friendship_id' => $friendship->id,
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'email' => $friend->email,
                    'profile_picture' => $friend->profile_picture,
                    'since' => $friendship->updated_at->format('Y-m-d'),
                    'status' => $friendship->status    
                ];
            });

        return response()->json($friends);
    }

    /**
     * Display pending friend requests for the user.
     *
     * @return \Illuminate\Http\Response
     */
    public function requests()
    {
        $userId = Auth::id();

        // Get pending friend requests (where user is the recipient)
        $pendingRequests = Friendship::where('friend_id', $userId)
            ->where('status', 'pending')
            ->with('user:id,name,email,profile_picture')
            ->get()
            ->map(function($friendship) {
                return [
                    'request_id' => $friendship->id,
                    'user' => [
                        'id' => $friendship->user->id,
                        'name' => $friendship->user->name,
                        'email' => $friendship->user->email,
                        'profile_picture' => $friendship->user->profile_picture,
                    ],
                    'created_at' => $friendship->created_at->format('Y-m-d')
                ];
            });

        return response()->json($pendingRequests);
    }

    /**
     * Display sent friend requests that are pending.
     *
     * @return \Illuminate\Http\Response
     */
    public function sentRequests()
    {
        $userId = Auth::id();

        // Get pending requests sent by the user
        $sentRequests = Friendship::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('friend:id,name,email,profile_picture')
            ->get()
            ->map(function($friendship) {
                return [
                    'request_id' => $friendship->id,
                    'user' => [
                        'id' => $friendship->friend->id,
                        'name' => $friendship->friend->name,
                        'email' => $friendship->friend->email,
                        'profile_picture' => $friendship->friend->profile_picture,
                    ],
                    'created_at' => $friendship->created_at->format('Y-m-d')
                ];
            });

        return response()->json($sentRequests);
    }

    /**
     * Display a specific friendship.
     *
     * @param Friendship $friendship
     * @return \Illuminate\Http\Response
     */
    public function show(Friendship $friendship)
    {
        $userId = Auth::id();

        // Ensure user can only see their own friendships
        if ($friendship->user_id !== $userId && $friendship->friend_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $friendship->load('user', 'friend');
    }

    /**
     * Store a newly created friendship in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $userId = Auth::id();

        $request->validate([
            'friend_id' => 'required|exists:users,id|different:user_id',
        ]);

        // Check if friendship already exists
        $exists = Friendship::where(function($query) use ($userId, $request) {
                $query->where('user_id', $userId)
                      ->where('friend_id', $request->friend_id);
            })
            ->orWhere(function($query) use ($userId, $request) {
                $query->where('user_id', $request->friend_id)
                      ->where('friend_id', $userId);
            })
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Friendship or request already exists'], 422);
        }

        $friendship = Friendship::create([
            'user_id' => $userId,
            'friend_id' => $request->friend_id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Friend request sent successfully',
            'friendship' => $friendship
        ], 201);
    }

    /**
     * Update the specified friendship to accepted state.
     *
     * @param  Friendship  $friendship
     * @return \Illuminate\Http\Response
     */
    public function update(Friendship $friendship)
    {
        $userId = Auth::id();

        // Can only accept if user is the recipient
        if ($friendship->friend_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($friendship->status=='accepted') {
            return response()->json(['message' => 'Friend request already accepted']);
        }

        $friendship->update(['status' => 'accepted']);

        return response()->json(['message' => 'Friend request accepted']);
    }

    /**
     * Remove the specified friendship.
     *
     * @param  Friendship  $friendship
     * @return \Illuminate\Http\Response
     */
    public function destroy(Friendship $friendship)
    {
        $userId = Auth::id();

        // Can only delete if user is part of the friendship
        if ($friendship->user_id !== $userId && $friendship->friend_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $friendship->delete();

        return response()->json(['message' => 'Friendship removed successfully']);
    }

    public function search(Request $request)
{
    $request->validate([
        'query' => 'required|string|max:255',
    ]);

    $userId = Auth::id();
    $query = $request->input('query');

    $excludeUserIds = User::whereHas('friendships', function ($q) use ($userId) {
        $q->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('friend_id', $userId);
        });
    })->pluck('id')->toArray();

    $excludeUserIds[] = $userId;

    $users = User::select('id', 'name', 'email', 'profile_picture')
        ->where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })
        ->whereNotIn('id', $excludeUserIds)
        ->limit(10)
        ->get();

    return response()->json($users);
}
}
