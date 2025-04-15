<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
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
                      ->where('accepted', true);
            })
            ->orWhere(function($query) use ($userId) {
                $query->where('friend_id', $userId)
                      ->where('accepted', true);
            })
            ->with(['user:id,name,email,img_url', 'friend:id,name,email,img_url'])
            ->get()
            ->map(function($friendship) use ($userId) {
                // Determine which user is the friend (not the current user)
                $friend = $friendship->user_id === $userId ? $friendship->friend : $friendship->user;
                return $friend;
            });

        return response()->json($friends);
    }
}
