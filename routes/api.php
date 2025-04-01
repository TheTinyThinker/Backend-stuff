<?php

use App\Http\Controllers\QuizController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
// Public auth routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});

// Public quiz routes (available to everyone)
Route::get('quizzes/public', [QuizController::class, 'publicQuizzes']);

// Private quiz routes (available only to authenticated users)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('quizzes/private', [QuizController::class, 'privateQuizzes']);
});


/*
|--------------------------------------------------------------------------
| User & Profile Routes
|--------------------------------------------------------------------------
*/
// Public user stats routes
Route::get('/users/{id}/stats', [UserController::class, 'show']);
Route::get('/users/{id}/detailed-stats', [UserController::class, 'getDetailedStats']);

// Protected profile routes
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/profile', [ProfileController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Quiz Management Routes
|--------------------------------------------------------------------------
*/
// Public quiz routes
Route::get('quizzes', [QuizController::class, 'index']);
Route::get('quizzes/{id}', [QuizController::class, 'show']);

// Protected quiz routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('quizzes', [QuizController::class, 'store']);
    Route::post('quizzes/{id}', [QuizController::class, 'update']);
    Route::delete('quizzes/{id}', [QuizController::class, 'destroy']);
    Route::post('/quizzes/{id}/rate', [QuizController::class, 'rateQuiz']);

    // Questions & Answers management
    Route::apiResource('questions', QuestionController::class);
    Route::apiResource('answers', AnswerController::class);
    Route::post('check-answer', [AnswerController::class, 'checkAnswer']);
});

/*
|--------------------------------------------------------------------------
| Room & Lobby Management Routes
|--------------------------------------------------------------------------
*/
// Room creation & joining
Route::post('/rooms/create', [RoomController::class, 'createRoom']);
Route::post('/rooms/join', [RoomController::class, 'joinRoom']);
Route::post('/rooms/leave', [RoomController::class, 'leaveRoom']);
Route::get('/rooms/info', [RoomController::class, 'getRoomInfo']);

// Host privileges
Route::post('/rooms/select-quiz', [RoomController::class, 'selectQuiz']);
Route::post('/rooms/toggle-status', [RoomController::class, 'toggleRoomStatus']);
Route::post('/rooms/set-voting-method', [RoomController::class, 'setVotingMethod']);

// Quiz voting system
Route::post('/rooms/suggest-quiz', [RoomController::class, 'suggestQuiz']);
Route::post('/rooms/vote', [RoomController::class, 'voteForQuiz']);
Route::post('/rooms/select-voted-quiz', [RoomController::class, 'selectVotedQuiz']);
Route::post('/rooms/select-highest-voted', [RoomController::class, 'selectHighestVotedQuiz']);

// Testing (development only)
Route::post('send-test-broadcast', [RoomController::class, 'sendTestBroadcast']);

/*
|--------------------------------------------------------------------------
| Game Routes
|--------------------------------------------------------------------------
*/
Route::post('/game/start', [GameController::class, 'startQuiz']);
Route::post('/game/submit-answer', [GameController::class, 'submitAnswer']);

/*
|--------------------------------------------------------------------------
| Leaderboard & Social Routes
|--------------------------------------------------------------------------
*/
// Public leaderboard routes
Route::get('/leaderboard/results', [LeaderboardController::class, 'getResults']);

// Protected social & leaderboard routes
Route::middleware('auth:sanctum')->group(function () {
    // Friendships
    Route::apiResource('friendships', FriendshipController::class);

    // Leaderboard
    Route::get('leaderboard', [LeaderboardController::class, 'index']);
    Route::get('user-rank/{user_id}', [LeaderboardController::class, 'userRank']);
});


Route::middleware('api')->group(function () {
    // Your API endpoints here
    Route::get('/data', [YourController::class, 'getData']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/protected-data', [YourController::class, 'getProtectedData']);
    });
});
