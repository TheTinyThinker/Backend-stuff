<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Events\QuestionSent;
use App\Events\PlayerAnswered;
use App\Events\QuizEnded;
use App\Models\User;

class GameController extends Controller
{
    public function startQuiz(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');

        try {
            $roomData = Cache::get("room:$roomCode");

            if (!$roomData) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            // Check if user is host
            $users = $roomData['users'];
            $user = collect($users)->firstWhere('id', $userId);

            if (!$user || !$user['isHost']) {
                return response()->json(['error' => 'Only the host can start the quiz'], 403);
            }

            // When starting a quiz, increment play count
            $quizId = $roomData['selectedQuiz']['id'];
            $quiz = Quiz::findOrFail($quizId);
            $quiz->increment('play_count');

            // Get full quiz with questions and answers
            $quizId = $roomData['selectedQuiz']['id'];
            $quiz = Quiz::with(['questions' => function($query) {
                $query->with(['answers']);
            }])->findOrFail($quizId);

            // Initialize game state
            $gameState = [
                'status' => 'active',
                'currentQuestionIndex' => 0,
                'questions' => $quiz->questions->map(function($question) {
                    return [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'question_type' => $question->question_type,
                        'img_url' => $question->img_url,
                        'time_to_answer' => $question->time_to_answer ?? 30,
                        'answers' => $question->answers->map(function($answer) {
                            return [
                                'id' => $answer->id,
                                'answer_text' => $answer->answer_text,
                                'is_correct' => $answer->is_correct
                            ];
                        })
                    ];
                }),
                'playerAnswers' => [],
                'results' => []
            ];

            //here this is cos joining mid game is a mess and we need to put an end to it
            $roomData['isGameStarted'] = true;
            // Save updated room data
            $roomData['gameState'] = $gameState;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            //here i just ended up using the first next quesion appearing as a signal to start so we dont need quiz started event
            // Broadcast first question
            $question = $this->prepareQuestionForBroadcast($gameState['questions'][0]);
            event(new QuestionSent($roomCode, $question, 1, count($gameState['questions'])));

            return response()->json([
                'message' => 'Quiz started',
                'currentQuestion' => $question,
                'questionNumber' => 1,
                'totalQuestions' => count($gameState['questions']),
                'isGameStarted' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to start quiz: ' . $e->getMessage()], 500);
        }
    }

    public function calculateLeaderboard($roomData, $gameState)
    {
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

        // Sort leaderboard by score in descending order
        usort($leaderboard, fn($a, $b) => $b['score'] - $a['score']);

        return $leaderboard;
    }

    public function submitAnswer(Request $request)
    {
        $request->validate([
            'roomCode' => 'required|string|size:6',
            'userId' => 'required',
            'questionId' => 'required|integer',
            'answerId' => 'nullable',
        ]);

        $roomCode = $request->input('roomCode');
        $userId = $request->input('userId');
        $questionId = $request->input('questionId');
        $answerId = $request->input('answerId');

        try {
            $roomData = Cache::get("room:$roomCode");

            if (!$roomData || !isset($roomData['gameState'])) {
                return response()->json(['error' => 'Game not in progress'], 404);
            }

            $gameState = $roomData['gameState'];

            // Record the answer
            if (!isset($gameState['playerAnswers'][$userId])) {
                $gameState['playerAnswers'][$userId] = [];
            }

            $gameState['playerAnswers'][$userId][$questionId] = $answerId;

            // Update the room data
            $roomData['gameState'] = $gameState;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            //here counting answers to current question
            $answeredCount = 0;
            foreach ($roomData['users'] as $user) {
                if (isset($gameState['playerAnswers'][$user['id']][$questionId])) {
                    $answeredCount++;
                };
            };

            // Broadcast that a player has answered
            event(new PlayerAnswered($roomCode, $userId, $answeredCount));

            // Check if all players have answered
            $allAnswered = true;
            foreach ($roomData['users'] as $user) {
                if (!isset($gameState['playerAnswers'][$user['id']][$questionId])) {
                    $allAnswered = false;
                    break;
                }
            }

            if ($allAnswered) {
                // Get current question
                $currentQuestion = null;
                foreach ($gameState['questions'] as $question) {
                    if ($question['id'] == $questionId) {
                        $currentQuestion = $question;
                        break;
                    }
                }

                // Calculate results for this question
                $questionResults = [];
                foreach ($roomData['users'] as $user) {
                    $userId = $user['id'];
                    $answerId = $gameState['playerAnswers'][$userId][$questionId] ?? null;

                    if ($answerId) {
                        $isCorrect = false;
                        foreach ($currentQuestion['answers'] as $answer) {
                            if ($answer['id'] == $answerId && $answer['is_correct']) {
                                $isCorrect = true;
                                break;
                            }
                        }

                        if (!isset($gameState['results'][$userId])) {
                            $gameState['results'][$userId] = [
                                'correct' => 0,
                                'incorrect' => 0,
                                'score' => 0
                            ];
                        }

                        if ($isCorrect) {
                            $gameState['results'][$userId]['correct']++;
                            $gameState['results'][$userId]['score'] += 100;
                        } else {
                            $gameState['results'][$userId]['incorrect']++;
                        }

                        $questionResults[$userId] = [
                            'answerId' => $answerId,
                            'isCorrect' => $isCorrect
                        ];
                    }
                }

                // here i decided its easier for me to move the reveal answer into the next question as optional data
                // Reveal the answer
                //event(new RevealAnswer($roomCode, $questionId, $currentQuestion['answers'], $questionResults));

                // Move to next question after delay
                $currentIndex = $gameState['currentQuestionIndex'];
                $nextIndex = $currentIndex + 1;

                if ($nextIndex < count($gameState['questions'])) {
                    // Update game state
                    $gameState['currentQuestionIndex'] = $nextIndex;
                    $roomData['gameState'] = $gameState;
                    Cache::put("room:$roomCode", $roomData, now()->addHours(2));

                    // Send next question after delay
                    $nextQuestion = $this->prepareQuestionForBroadcast($gameState['questions'][$nextIndex]);

                    // In a real application, you might want to use a job queue for this delay
                    // For now, we'll just broadcast immediately
                    event(new QuestionSent($roomCode, $nextQuestion, $nextIndex + 1, count($gameState['questions']), $currentQuestion['answers'], $questionResults));
                } else {
                    // End the quiz
                    $gameState['status'] = 'ended';
                    $roomData['gameState'] = $gameState;
                    Cache::put("room:$roomCode", $roomData, now()->addHours(2));

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

                    $roomData['isGameStarted'] = false;


                    // ADDED THE QUIZ STATS TRACKING CODE HERE
                    $totalAnswers = 0;
                    $correctAnswers = 0;

                    foreach ($gameState['playerAnswers'] as $playerAnswers) {
                        foreach ($playerAnswers as $questionId => $answerId) {
                            $totalAnswers++;

                            // Find if this was a correct answer
                            foreach ($gameState['questions'] as $question) {
                                if ($question['id'] == $questionId) {
                                    foreach ($question['answers'] as $answer) {
                                        if ($answer['id'] == $answerId && $answer['is_correct']) {
                                            $correctAnswers++;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($totalAnswers > 0) {
                        $quizId = $roomData['selectedQuiz']['id'];
                        $correctPercentage = ($correctAnswers / $totalAnswers) * 100;

                        // Update running average
                        $quiz = Quiz::findOrFail($quizId);
                        $oldPercentage = $quiz->correct_answer_percentage;
                        $oldPlayCount = $quiz->play_count;

                        if ($oldPlayCount > 0) {
                            $newPercentage = (($oldPercentage * ($oldPlayCount - 1)) + $correctPercentage) / $oldPlayCount;
                            $quiz->update(['correct_answer_percentage' => $newPercentage]);
                        } else {
                            $quiz->update(['correct_answer_percentage' => $correctPercentage]);
                        }
                    }

                    // THEN dispatch the event

                    foreach ($gameState['results'] as $userId => $result) {
                        $user = User::find($userId);

                        // Update total_quizzes_attempted
                        $user->increment('total_quizzes_attempted');

                        // Refresh user data to get updated values
                        $user = $user->fresh();
                        $quizCount = $user->total_quizzes_attempted;

                        // Failsafe: Make sure we don't divide by zero
                        if ($quizCount <= 0) {
                            $quizCount = 1;
                        }

                        // Update highest_score if current score is higher
                        if ($result['score'] > $user->highest_score) {
                            $user->update(['highest_score' => $result['score']]);
                        }

                        // Update average_score
                        $newAverage = (($user->average_score * ($quizCount - 1)) + $result['score']) / $quizCount;
                        $user->update(['average_score' => round($newAverage, 2)]);

                        // Create persistent leaderboard entry
                        \App\Models\Leaderboard::create([
                            'user_id' => $userId,
                            'quiz_id' => $roomData['selectedQuiz']['id'],
                            'points' => $result['score']
                        ]);
                    }

                    $leaderboard = $this->calculateLeaderboard($roomData, $gameState);

                    event(new QuizEnded($roomCode, $leaderboard, $currentQuestion['answers'], $questionResults));
                }
            }

            $isCorrect = false;
            foreach ($currentQuestion['answers'] as $answer) {
                if ($answer['id'] == $answerId && $answer['is_correct']) {
                    $isCorrect = true;
                    break;
                }
            }

            // Update user stats
            $user = User::find($userId);
            if ($isCorrect) {
                $user->increment('correct_answers');
                $user->increment('total_score', 100); // Base points
            } else {
                $user->increment('incorrect_answers');
            }
            $user->increment('total_questions_answered');

            // Recalculate percentage
            $totalAnswers = $user->correct_answers + $user->incorrect_answers;
            if ($totalAnswers > 0) {
                $user->update([
                    'correct_percentage' => ($user->correct_answers / $totalAnswers) * 100
                ]);
            }

            return response()->json(['message' => 'Answer submitted successfully']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to submit answer: ' . $e->getMessage()], 500);
        }
    }

    public function prepareQuestionForBroadcast($question)
    {

        $answers = collect($question['answers'])->map(function($answer) {
            return [
                'id' => $answer['id'],
                'answer_text' => $answer['answer_text']
                // Note: is_correct is intentionally removed
            ];
        });

        return [
            'id' => $question['id'],
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'img_url' => $question['img_url'],
            'time_to_answer' => $question['time_to_answer'],
            'answers' => $answers
        ];
    }

    /**
     * Suggest a quiz for the room
     */
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

            $quiz = Quiz::find($quizId);
            if (!$quiz) {
                return response()->json(['error' => 'Quiz not found'], 404);
            }

            // Get user name
            $userName = '';
            foreach ($roomData['users'] as $user) {
                if ($user['id'] === $userId) {
                    $userName = $user['name'];
                    break;
                }
            }

            // Add to suggested quizzes
            if (!isset($roomData['suggestedQuizzes'])) {
                $roomData['suggestedQuizzes'] = [];
            }

            // Check if already suggested
            $alreadySuggested = false;
            foreach ($roomData['suggestedQuizzes'] as $suggested) {
                if ($suggested['id'] === $quiz->id) {
                    $alreadySuggested = true;
                    break;
                }
            }

            if (!$alreadySuggested) {
                $roomData['suggestedQuizzes'][] = [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'img_url' => $quiz->img_url,
                    'category' => $quiz->category,
                    'length' => $quiz->questions()->count(),
                    'suggestedBy' => [
                        'id' => $userId,
                        'name' => $userName
                    ],
                    'votes' => []
                ];

                Cache::put("room:$roomCode", $roomData, now()->addHours(2));

                // Create a new event for quiz suggestion
                event(new QuizSuggested($roomCode, $roomData['suggestedQuizzes']));
            }

            return response()->json([
                'message' => 'Quiz suggested',
                'suggestedQuizzes' => $roomData['suggestedQuizzes']
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to suggest quiz: ' . $e->getMessage()], 500);
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

            if (!isset($roomData['suggestedQuizzes'])) {
                return response()->json(['error' => 'No suggested quizzes found'], 404);
            }

            $quizFound = false;
            foreach ($roomData['suggestedQuizzes'] as $key => $quiz) {
                if ($quiz['id'] === $quizId) {
                    // Check if user already voted
                    if (!in_array($userId, $quiz['votes'])) {
                        $roomData['suggestedQuizzes'][$key]['votes'][] = $userId;
                    }
                    $quizFound = true;
                    break;
                }
            }

            if (!$quizFound) {
                return response()->json(['error' => 'Suggested quiz not found'], 404);
            }

            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            // Create a new event for vote update
            event(new QuizVotesUpdated($roomCode, $roomData['suggestedQuizzes']));

            return response()->json([
                'message' => 'Vote recorded',
                'suggestedQuizzes' => $roomData['suggestedQuizzes']
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to record vote'], 500);
        }
    }

    /**
     * Select a suggested quiz (host only)
     */
    public function selectSuggestedQuiz(Request $request)
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

            // Check if user is host
            $isHost = false;
            foreach ($roomData['users'] as $user) {
                if ($user['id'] === $userId && $user['isHost']) {
                    $isHost = true;
                    break;
                }
            }

            if (!$isHost) {
                return response()->json(['error' => 'Only the host can select quizzes'], 403);
            }

            // Find the quiz in suggestions
            $selectedQuiz = null;
            foreach ($roomData['suggestedQuizzes'] as $quiz) {
                if ($quiz['id'] === $quizId) {
                    $selectedQuiz = $quiz;
                    break;
                }
            }

            if (!$selectedQuiz) {
                // Try to find in the database directly
                $quiz = Quiz::find($quizId);
                if (!$quiz) {
                    return response()->json(['error' => 'Quiz not found'], 404);
                }

                $selectedQuiz = [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'img_url' => $quiz->img_url,
                    'category' => $quiz->category,
                    'length' => $quiz->questions()->count(),
                ];
            }

            // Update the selected quiz
            $roomData['selectedQuiz'] = $selectedQuiz;
            Cache::put("room:$roomCode", $roomData, now()->addHours(2));

            // Broadcast quiz selection
            event(new QuizSelected($roomCode, $selectedQuiz));

            return response()->json([
                'message' => 'Quiz selected',
                'selectedQuiz' => $selectedQuiz
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to select quiz'], 500);
        }
    }
}
