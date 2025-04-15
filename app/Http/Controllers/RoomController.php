<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Routing\Controller;
use App\Events\CreateRoom;
use App\Events\UserChange;
use App\Events\UserLeft;
use App\Events\QuizSelected;
use App\Events\QuizSuggested;
use App\Events\RoomStatusChanged;
use App\Events\SuggestStatusChanged;
use App\Events\InviteSent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\GameController;

class RoomController extends Controller
{

    protected $gameController;

    // Inject GameController into the constructor
    public function __construct(GameController $gameController)
    {
        $this->gameController = $gameController;
    }

    public function handleAnswerSubmission($roomCode, $userId, $questionId, $answerId = null)
    {
        $request = new Request([
            'roomCode' => $roomCode,
            'userId' => $userId,
            'questionId' => $questionId,
            'answerId' => $answerId,
        ]);

        return $this->gameController->submitAnswer($request);
    }


    public function createRoom(Request $request)
    {
        $request->validate([
            'userName' => 'required|string|max:255',
            'userId' => 'required',
            'quizId' => 'nullable|integer',
            'votingMethod' => 'nullable|string|in:manual,automatic', // Add this
        ]);

        // Get the voting method, default to manual if not provided
        $votingMethod = $request->input('votingMethod', 'manual');

        $roomCode = Str::upper(Str::random(6));
        $userName = $request->input('userName');
        $userId = $request->input('userId');
        $quizId = $request->input('quizId');

        try {

            $user = User::find($userId);
            $profilePicture = $user ? $user->profile_picture : null;

            $host = [
                'id' => $userId,
                'name' => $userName,
                'isHost' => true,
                'profilePicture' => $profilePicture
            ];
            $roomData = [
                'users' => [$host],
                'selectedQuiz' => null,
                'isGameStarted' => false,
                'isOpen' => true, // Add this
                'canSuggest' => $roomData['canSuggest'] ?? true,
                'votingMethod' => $votingMethod, // Add this
                'suggestedQuizzes' => [], // Add this
            ];

            if ($quizId) {
                $quiz = Quiz::find($quizId);

                if (!$quiz) {
                    return response()->json(['error' => 'Quiz not found'], 404);
                }

                $roomData['selectedQuiz'] = [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'img_url' => $quiz->img_url,
                    'created_by' => $quiz->created_by,
                    'average_rating' => $quiz->average_rating,
                    'category' => $quiz->category,
                    'length' => $quiz->questions()->count(),
                ];

                event(new QuizSelected($roomCode, $roomData['selectedQuiz']));
            }

            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            event(new CreateRoom($roomCode));
            event(new UserChange($roomCode, [$host]));

            return response()->json([
                'room_code' => $roomCode,
                'users' => [$host],
                'selectedQuiz' => $roomData['selectedQuiz'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to Create Room'], 500);
        }
    }


    public function joinRoom(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userName' => 'required|string|max:255',
            'userId' => 'required',
        ]);

        $roomCode = $request->input('roomCode');
        $userName = $request->input('userName');
        $userId = $request->input('userId');

        try {
            $roomData = Cache::get("room:$roomCode", null);

            if ($roomData === null) {
                return response()->json(['error' => 'This room does not exist'], 404);
            }

            if ($roomData['isGameStarted']) {
                return response()->json(['error' => 'Game has already started'], 400);
            }

            if (!$roomData['isOpen']) {
                return response()->json(['error' => 'This room is closed'], 400);
            }

            $users = $roomData['users'];
            $existingUser = array_filter($users, fn($user) => $user['id'] === $userId);
            if (!empty($existingUser)) {
                return response()->json(['error' => 'User already in the room'], 400);
            }

            $user = User::find($userId);
            $profilePicture = $user ? $user->profile_picture : null;

            $users[] = [
                'id' => $userId,
                'name' => $userName,
                'isHost' => false,
                'profilePicture' => $profilePicture
            ];

            // Update the cache with the latest users list
            Cache::put("room:$roomCode", [
                'users' => $users,
                'selectedQuiz' => $roomData['selectedQuiz'] ?? null, // Ensure selectedQuiz is preserved
                'isGameStarted' => $roomData['isGameStarted'] ?? false,
                'isOpen' => $roomData['isOpen'] ?? true,
                'canSuggest' => $roomData['canSuggest'] ?? true,
                'suggestedQuizzes' => $roomData['suggestedQuizzes'] ?? []
            ], now()->addHours(2));

            event(new UserChange($roomCode, $users));

            return response()->json([
                'message' => "$userName joined room $roomCode",
                'users' => $users,
                'selectedQuiz' => $roomData['selectedQuiz'] ?? null,
                'isGameStarted' => $roomData['isGameStarted'] ?? false,
                'isOpen' => $roomData['isOpen'] ?? true,
                'canSuggest' => $roomData['canSuggest'] ?? true,
                'suggestedQuizzes' => $roomData['suggestedQuizzes'] ?? []
            ]);
        } catch (\Exception $e) {
            \Log::error('Error joining room: ' . $e->getMessage(), [
                'exception' => $e,
                'stack' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to Join Room'], 500);
        }
    }


    public function leaveRoom(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');

        try {
            $roomData = Cache::get("room:$roomCode", []);
            $users = $roomData['users'];
            $gameState = $roomData['gameState'] ?? null;

            $userIndex = array_search($userId, array_column($users, 'id'));

            if ($userIndex === false) {
                return response()->json(['error' => 'User not found in the room'], 404);
            }

            $isHost = $users[$userIndex]['isHost'];
            unset($users[$userIndex]);

            if ($isHost && count($users) > 0) {
                $users = array_values($users);
                $users[0]['isHost'] = true;
            }

            // Handle answer submission (empty answer for user leaving)
            try {
                $this->handleAnswerSubmission(
                    $roomCode,
                    $userId,
                    $gameState['questions'][$gameState['currentQuestionIndex']]['id'],
                    -1  // passing null as answerId for an empty answer
                );
            } catch (\Exception $e) {
                \Log::error('Error while sending empty answer: ' . $e->getMessage());
            }

            // Update the cache with the latest room data
            Cache::put("room:$roomCode", [
                'users' => $users,
                'selectedQuiz' => $roomData['selectedQuiz'] ?? null,
                'isGameStarted' => $roomData['isGameStarted'] ?? false,
                'gameState' => $roomData['gameState'] ?? null,
                'isOpen' => $roomData['isOpen'] ?? true,
                'canSuggest' => $roomData['canSuggest'] ?? true,
                'suggestedQuizzes' => $roomData['suggestedQuizzes'] ?? []
            ], now()->addHours(2));

            event(new UserChange($roomCode, $users));

            // If the room is empty after the user leaves, remove the cache
            if (empty($users)) {
                Cache::forget("room:$roomCode");
            }

            // Now recheck if all players have answered the current question
            try {
                $this->checkAllPlayersAnswered($roomCode, $roomData);
            } catch (\Exception $e) {
                \Log::error('Error while checking answers: ' . $e->getMessage());
            }

            return response()->json(['message' => "User $userId left room $roomCode"]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to Leave Room'], 500);
        }
    }

    protected function checkAllPlayersAnswered($roomCode, $roomData)
    {
        // Get the current question index
            $gameState = $roomData['gameState'];
            $questionId = $gameState['questions'][$gameState['currentQuestionIndex']]['id'];

            $allAnswered = true;
            foreach ($roomData['users'] as $user) {
                if (!isset($gameState['playerAnswers'][$user['id']][$questionId])) {
                    $allAnswered = false;
                    break;
                }
            }
            if ($allAnswered) {
                // All players have answered, handle moving to the next question or finalizing
                $this->moveToNextQuestionOrEndQuiz($roomCode, $gameState, $roomData);
            } else {

            }
    }

    protected function moveToNextQuestionOrEndQuiz($roomCode, $gameState, $roomData)
    {
        // Get current question
        $currentIndex = $gameState['currentQuestionIndex'];
        $nextIndex = $currentIndex + 1;

        if ($nextIndex < count($gameState['questions'])) {
            // Move to the next question
            $gameState['currentQuestionIndex'] = $nextIndex;
            $roomData['gameState'] = $gameState;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            $nextQuestion = $this->prepareQuestionForBroadcast($gameState['questions'][$nextIndex]);

            event(new QuestionSent($roomCode, $nextQuestion, $nextIndex + 1, count($gameState['questions'])));
        } else {
            // End the quiz
            $gameState['status'] = 'ended';
            $roomData['gameState'] = $gameState;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            $leaderboard = $this->calculateLeaderboard($gameState, $roomData);

            event(new QuizEnded($roomCode, $leaderboard));
        }
    }


    public function getRoomInfo(Request $request)
    {
        $roomCode = $request->query('roomCode');
        $roomData = Cache::get("room:$roomCode", []);

        if ($roomData) {
            return response()->json([
                'users' => $roomData['users'],
                'selectedQuiz' => $roomData['selectedQuiz'],
                'isOpen' => $roomData['isOpen'] ?? true,
                'canSuggest' => $roomData['canSuggest'] ?? true,
                'suggestedQuizzes' => $roomData['suggestedQuizzes'] ?? []
            ]);
        }

        return response()->json(['error' => 'Room not found'], 404);
    }

    public function selectQuiz(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'quizId' => 'required|integer',
        ]);

        $roomCode = $request->input('roomCode');
        $quizId = $request->input('quizId');

        try {
            $quiz = Quiz::find($quizId);

            if (!$quiz) {
                return response()->json(['error' => 'Quiz not found'], 404);
            }

            $roomData = Cache::get("room:$roomCode", []);

            $users = $roomData['users'];

            Cache::put("room:$roomCode", [
                'users' => $users,
                'selectedQuiz' => [ 
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'img_url' => $quiz->img_url,
                    'created_by' => $quiz->created_by,
                    'average_rating' => $quiz->average_rating,
                    'category' => $quiz->category,
                    'length' => $quiz->questions()->count(),
                ],
                'isGameStarted' => $quiz->isGameStarted,
                'isOpen' => $roomData['isOpen'] ?? true,
                'canSuggest' => $roomData['canSuggest'] ?? true,
                'suggestedQuizzes' => $roomData['suggestedQuizzes'],
            ], now()->addHours(2));

            event(new QuizSelected($roomCode, [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'img_url' => $quiz->img_url,
                'created_by' => $quiz->created_by,
                'average_rating' => $quiz->average_rating,
                'category' => $quiz->category,
                'length' => $quiz->questions()->count(),
            ]));

            return response()->json([
                'message' => 'Quiz selected',
                'quiz' => [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'img_url' => $quiz->img_url,
                    'created_by' => $quiz->created_by,
                    'average_rating' => $quiz->average_rating,
                    'category' => $quiz->category,
                    'length' => $quiz->questions()->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to select quiz'], 500);
        }
    }

    /**
     * Toggle room status (open/closed)
     */
    public function toggleRoomStatus(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
            'isOpen' => 'required|boolean',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');
        $isOpen = $request->input('isOpen');

        try {
            $roomData = Cache::get("room:$roomCode", null);

            if (!$roomData) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            // Check if user is host
            $isHost = false;
            foreach ($roomData['users'] as $user) {
                if ($user['id'] === $userId && $user['isHost']) {
                    $isHost = true;
                    break;
                }
            }

            if (!$isHost) {
                return response()->json(['error' => 'Only the host can change room status'], 403);
            }

            // Update room status
            $roomData['isOpen'] = $isOpen;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            // Create a new event for room status change
            event(new RoomStatusChanged($roomCode, $isOpen));

            return response()->json([
                'message' => 'Room status updated',
                'isOpen' => $isOpen,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update room status'], 500);
        }
    }

    public function toggleSuggestStatus(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
            'canSuggest' => 'required|boolean',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');
        $canSuggest = $request->input('canSuggest');

        try {
            $roomData = Cache::get("room:$roomCode", null);

            if (!$roomData) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            // Check if user is host
            $isHost = false;
            foreach ($roomData['users'] as $user) {
                if ($user['id'] === $userId && $user['isHost']) {
                    $isHost = true;
                    break;
                }
            }

            if (!$isHost) {
                return response()->json(['error' => 'Only the host can change suggest status'], 403);
            }

            // Update room status
            $roomData['canSuggest'] = $canSuggest;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            // Create a new event for room status change
            event(new SuggestStatusChanged($roomCode, $canSuggest));

            return response()->json([
                'message' => 'Room status updated',
                'canSuggest' => $canSuggest,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update suggest status'], 500);
        }
    }



    /**
     * Select highest voted quiz automatically
     */
    public function selectHighestVotedQuiz(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');

        try {
            $roomData = Cache::get("room:$roomCode", null);

            if (!$roomData) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            // Check if user is host
            $isHost = false;
            foreach ($roomData['users'] as $user) {
                if ($user['id'] === $userId && $user['isHost']) {
                    $isHost = true;
                    break;
                }
            }

            if (!$isHost) {
                return response()->json(['error' => 'Only the host can start voting process'], 403);
            }

            if (!isset($roomData['suggestedQuizzes']) || count($roomData['suggestedQuizzes']) === 0) {
                return response()->json(['error' => 'No quizzes have been suggested'], 404);
            }

            // Find the quiz with the most votes
            $highestVotes = -1;
            $selectedQuiz = null;

            foreach ($roomData['suggestedQuizzes'] as $quiz) {
                $voteCount = count($quiz['votes'] ?? []);
                if ($voteCount > $highestVotes) {
                    $highestVotes = $voteCount;
                    $selectedQuiz = $quiz;
                }
            }

            if (!$selectedQuiz) {
                return response()->json(['error' => 'No quiz found with votes'], 404);
            }

            // Update the room with the selected quiz
            $roomData['selectedQuiz'] = [
                'id' => $selectedQuiz['id'],
                'title' => $selectedQuiz['title'],
                'description' => $selectedQuiz['description'],
                'img_url' => $selectedQuiz['img_url'],
                'created_by' => $selectedQuiz['created_by'],
                'average_rating' => $selectedQuiz['average_rating'],
                'category' => $selectedQuiz['category'],
                'length' => $selectedQuiz['length'],
                'votes' => $highestVotes,
            ];

            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            // Broadcast the selected quiz
            event(new QuizSelected($roomCode, $roomData['selectedQuiz']));

            return response()->json([
                'message' => 'Highest voted quiz selected automatically',
                'selectedQuiz' => $roomData['selectedQuiz'],
                'voteCount' => $highestVotes
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to select highest voted quiz: ' . $e->getMessage()], 500);
        }
    }



    public function suggestQuiz(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
            'quizId' => 'required|integer',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');
        $quizId = $request->input('quizId');

        try {
            $roomData = Cache::get("room:$roomCode", null);

            if (!$roomData) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            if (!$roomData['canSuggest']) {
                return response()->json(['error' => 'Suggestions are disallowed'], 400);
            }

            if ($roomData['isGameStarted']) {
                return response()->json(['error' => 'Cannot suggest quizzes after the game has started'], 400);
            }

            $quiz = Quiz::find($quizId);
            if (!$quiz) {
                return response()->json(['error' => 'Quiz not found'], 404);
            }

            // Check if quiz is already suggested
            try {
                foreach ($roomData['suggestedQuizzes'] as &$suggestedQuiz) {
                    if ($suggestedQuiz['id'] == $quizId) {
                        return response()->json(['error' => 'Quiz already suggested'], 400);
                    }
                }
            }
            catch (\Exception $e)  {

            };

            // Add quiz to suggestions
            $roomData['suggestedQuizzes'][] = [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'img_url' => $quiz->img_url,
                'created_by' => $quiz->created_by,
                'average_rating' => $quiz->average_rating,
                'category' => $quiz->category,
                'length' => $quiz->questions()->count(),
                'votes' => [],
            ];

            Cache::put("room:$roomCode", $roomData, now()->addHours(2));
            event(new QuizSuggested($roomCode, $roomData['suggestedQuizzes']));

            return response()->json([
                'message' => 'Quiz suggested successfully',
                'suggestedQuizzes' => $roomData['suggestedQuizzes'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to suggest quiz'], 500);
        }
    }

    /**
     * Vote for a suggested quiz
     */
    public function voteForQuiz(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
            'quizId' => 'required|integer',
        ]);
    
        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');
        $quizId = $request->input('quizId');
    
        try {
            $roomData = Cache::get("room:$roomCode", null);
    
            if (!$roomData) {
                return response()->json(['error' => 'Room not found'], 404);
            }
    
            if ($roomData['isGameStarted']) {
                return response()->json(['error' => 'Cannot vote after the game has started'], 400);
            }
    
            $voteAdded = false;
            foreach ($roomData['suggestedQuizzes'] as &$suggestedQuiz) {
                // Remove user vote from all quizzes
                if (in_array($userId, $suggestedQuiz['votes'])) {
                    $suggestedQuiz['votes'] = array_filter(
                        $suggestedQuiz['votes'],
                        fn($voterId) => $voterId !== $userId
                    );
                }
    
                // Add vote to the selected quiz
                if ($suggestedQuiz['id'] == $quizId) {
                    $suggestedQuiz['votes'][] = $userId;
                    $voteAdded = true;
                }
            }
    
            if (!$voteAdded) {
                return response()->json(['error' => 'Quiz not found in suggestions'], 404);
            }
    
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));
            event(new QuizSuggested($roomCode, $roomData['suggestedQuizzes']));
    
            return response()->json([
                'message' => 'Vote updated successfully',
                'suggestedQuizzes' => $roomData['suggestedQuizzes'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to vote for quiz'], 500);
        }
    }
    
    public function inviteToRoom(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',       
            'roomCode' => 'required|string|size:6',
            'invitedId' => 'required|exists:users,id' 
        ]);
    
        $user = User::find($request->input('userId'));
        $invitedUser = User::find($request->input('invitedId'));
        $roomCode = $request->input('roomCode');
    
        // Broadcast the event
        event(new InviteSent($user, $invitedUser, $roomCode));
    
        return response()->json(['message' => 'Invite sent']);
    }
    

}


    /**
     * Host selects quiz from suggestions
     */
    // public function selectVotedQuiz(Request $request)
    // {
    //     $request->validate([
    //         'roomCode' => 'required|string|size:6',
    //         'userId' => 'required',
    //         'quizId' => 'required|integer',
    //     ]);

    //     $roomCode = $request->input('roomCode');
    //     $userId = $request->input('userId');
    //     $quizId = $request->input('quizId');

    //     try {
    //         $roomData = Cache::get("room:$roomCode", null);

    //         if (!$roomData) {
    //             return response()->json(['error' => 'Room not found'], 404);
    //         }

    //         // Check if user is host
    //         $isHost = false;
    //         foreach ($roomData['users'] as $user) {
    //             if ($user['id'] === $userId && $user['isHost']) {
    //                 $isHost = true;
    //                 break;
    //             }
    //         }

    //         if (!$isHost) {
    //             return response()->json(['error' => 'Only the host can select quizzes'], 403);
    //         }

    //         // Check if the quiz is in the suggested list
    //         $selectedQuizData = null;
    //         foreach ($roomData['suggestedQuizzes'] as $quiz) {
    //             if ($quiz['id'] == $quizId) {
    //                 $selectedQuizData = $quiz;
    //                 break;
    //             }
    //         }

    //         if (!$selectedQuizData) {
    //             return response()->json(['error' => 'Quiz not found in suggestions'], 404);
    //         }

    //         // Update the room with the selected quiz
    //         $roomData['selectedQuiz'] = [
    //             'id' => $selectedQuizData['id'],
    //             'title' => $selectedQuizData['title'],
    //             'description' => $selectedQuizData['description'],
    //             'img_url' => $selectedQuizData['img_url'],
    //             'created_by' => $selectedQuiz['created_by'],
    //             'category' => $selectedQuizData['category'],
    //             'length' => $selectedQuizData['length'],
    //             'votes' => count($selectedQuizData['votes']),
    //         ];

    //         Cache::put("room:$roomCode", $roomData, now()->addHours(2));

    //         // Broadcast the selected quiz
    //         event(new QuizSelected($roomCode, $roomData['selectedQuiz']));

    //         return response()->json([
    //             'message' => 'Quiz selected by host',
    //             'selectedQuiz' => $roomData['selectedQuiz']
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to select quiz: ' . $e->getMessage()], 500);
    //     }
    // }


        /**
     * Set the voting method for quiz selection
     */
    // public function setVotingMethod(Request $request)
    // {
    //     $request->validate([
    //         'roomCode' => 'required|string|size:6',
    //         'userId' => 'required',
    //         'votingMethod' => 'required|string|in:manual,automatic',
    //     ]);

    //     $roomCode = $request->input('roomCode');
    //     $userId = $request->input('userId');
    //     $votingMethod = $request->input('votingMethod');

    //     try {
    //         $roomData = Cache::get("room:$roomCode", null);

    //         if (!$roomData) {
    //             return response()->json(['error' => 'Room not found'], 404);
    //         }

    //         // Check if user is host
    //         $isHost = false;
    //         foreach ($roomData['users'] as $user) {
    //             if ($user['id'] === $userId && $user['isHost']) {
    //                 $isHost = true;
    //                 break;
    //             }
    //         }

    //         if (!$isHost) {
    //             return response()->json(['error' => 'Only the host can change voting settings'], 403);
    //         }

    //         // Update voting method
    //         $roomData['votingMethod'] = $votingMethod;
    //         Cache::put("room:$roomCode", $roomData, now()->addHours(2));

    //         // Create a new event for voting method change
    //         event(new VotingMethodChanged($roomCode, $votingMethod));

    //         return response()->json([
    //             'message' => 'Voting method updated',
    //             'votingMethod' => $votingMethod
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to update voting method'], 500);
    //     }
    // }