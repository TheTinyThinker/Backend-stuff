<?php
//HERE
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use App\Events\TestBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

}