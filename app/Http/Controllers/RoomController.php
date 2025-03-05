<?php
//HERE
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use App\Events\TestBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Room;

class RoomController extends Controller
{
    public function sendTestBroadcast()
    {
        $message = "This is a test broadcast from the backend/ this means the test worked!/ backend msg";
        try {
            event(new TestBroadcast($message));
            return response()->json(['message' => 'Test broadcast sent!/ backend msg']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send test broadcast/ backend msg'], 500);
        }
    }

    public function createRoom()
    {
        // Generate a 6-character unique uppercase code
        $roomCode = Str::upper(Str::random(6));

        // Store the room in the database
        $room = Room::create([
            'code' => $roomCode,
        ]);

        return response()->json(['room_code' => $room->code]);
    }

    /**
     * Join a room using a code.
     */
    public function joinRoom(Request $request)
    {
        // Validate input
        $request->validate([
            'room_code' => 'required|string|size:6',
        ]);

        // Check if the room exists
        $room = Room::where('code', $request->room_code)->first();

        if (!$room) {
            return response()->json(['error' => 'Invalid room code'], 404);
        }

        return response()->json([
            'success' => true,
            'room_code' => $room->code,
            'channel_name' => 'quiz-game.' . $room->id, // Use this for broadcasting
        ]);
    }

}
