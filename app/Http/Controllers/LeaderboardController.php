<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use App\Models\Leaderboard;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index()
    {
        return Leaderboard::with('user')->orderByDesc('points')->get();
    }

    public function userRank($user_id)
    {
        $rank = Leaderboard::where('points', '>', function ($query) use ($user_id) {
            $query->select('points')->from('leaderboards')->where('user_id', $user_id);
        })->count() + 1;

        return response()->json(['rank' => $rank]);
    }
}
