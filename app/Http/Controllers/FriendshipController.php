<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FriendshipController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'friend_id' => 'required|exists:users,id|different:user_id',
        ]);

        return Friendship::create($request->all());
    }

    public function update(Friendship $friendship)
    {
        $friendship->update(['accepted' => true]);
        return response()->json(['message' => 'Friend request accepted']);
    }
}
