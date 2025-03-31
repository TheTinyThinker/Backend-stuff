<?php

namespace App\Http\Controllers;

use App\Models\Leaderboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function getResults(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
        ]);

        $roomCode = $request->input('roomCode');

        try {
            $roomData = Cache::get("room:$roomCode");

            if (!$roomData || !isset($roomData['gameState'])) {
                return response()->json(['error' => 'Game not found'], 404);
            }

            $gameState = $roomData['gameState'];

            if ($gameState['status'] !== 'ended') {
                return response()->json(['error' => 'Quiz is still in progress'], 400);
            }

            // Calculate final leaderboard
            $leaderboard = [];
            foreach ($gameState['results'] as $userId => $result) {
                $user = collect($roomData['users'])->firstWhere('id', $userId);
                $leaderboard[] = [
                    'userId' => $userId,
                    'name' => $user['name'],
                    'profilePicture' => $user['profilePicture'],
                    'score' => $result['score'],
                    'correct' => $result['correct'],
                    'incorrect' => $result['incorrect']
                ];
            }

            // Sort by score descending
            usort($leaderboard, function($a, $b) {
                return $b['score'] - $a['score'];
            });

            return response()->json([
                'leaderboard' => $leaderboard,
                'quizTitle' => $roomData['selectedQuiz']['title']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get results: ' . $e->getMessage()], 500);
        }
    }
}
