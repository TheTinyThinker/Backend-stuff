<?php

use App\Http\Controllers\QuizController;
use App\Http\Controllers\ImageController;
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
use App\Http\Controllers\Admin;
use App\Http\Controllers\User;
use App\Http\Controllers\Guest;

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
    Route::post('/users/{id}/update', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
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
    Route::post('/quizzes/{id}/rate', [QuizController::class, 'rateQuiz']);
    Route::post('quizzes', [QuizController::class, 'store']);
    Route::post('quizzes/{id}', [QuizController::class, 'update']);
    Route::delete('quizzes/{id}', [QuizController::class, 'destroy']);

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
Route::post('/rooms/invite', [RoomController::class, 'inviteToRoom']);
Route::post('/rooms/join', [RoomController::class, 'joinRoom']);
Route::post('/rooms/leave', [RoomController::class, 'leaveRoom']);
Route::get('/rooms/info', [RoomController::class, 'getRoomInfo']);
Route::get('/rooms/friends-info', [RoomController::class, 'getFriendRooms']);

// Host privileges
Route::post('/rooms/select-quiz', [RoomController::class, 'selectQuiz']);
Route::post('/rooms/toggle-status', [RoomController::class, 'toggleRoomStatus']);
Route::post('/rooms/toggle-suggest', [RoomController::class, 'toggleSuggestStatus']);
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
// Route::post('/quizzes/{id}/rate', [QuizController::class, 'rateQuiz'])->middleware('auth');


//lobby / game stuff
// Route::post('/create-room', [RoomController::class, 'createRoom']);
// Route::post('/join-room', [RoomController::class, 'joinRoom']);
// Route::post('/leave-room', [RoomController::class, 'leaveRoom']);
// Route::get('/get-room-info', [RoomController::class, 'getRoomInfo']);
// Route::post('/select-quiz', [RoomController::class, 'selectQuiz']);

// Public quiz routes - explicitly define these outside auth middleware
// Route::get('quizzes', [QuizController::class, 'index']);
// Route::get('quizzes/{id}', [QuizController::class, 'show']);

// Route::get('/quizzes/{id}', [QuizController::class, 'show'])->middleware('auth:sanctum');


// Public endpoints - no authentication required
Route::get('quizzes', [QuizController::class, 'index']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('quizzes/{id}', [QuizController::class, 'show']);
    Route::post('quizzes/{id}', [QuizController::class, 'update']);  //update quiz
});


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Friendships
    Route::apiResource('friendships', FriendshipController::class);

    // Leaderboard
    Route::get('leaderboard', [LeaderboardController::class, 'index']);
    Route::get('user-rank/{user_id}', [LeaderboardController::class, 'userRank']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/friends', [FriendshipController::class, 'index']);
    Route::get('/friend-requests', [FriendshipController::class, 'requests']);
    Route::get('/sent-friend-requests', [FriendshipController::class, 'sentRequests']);
    Route::post('/friends', [FriendshipController::class, 'store']);
    Route::post('/friends/{friendship}', [FriendshipController::class, 'update']);
    Route::delete('/friends/{friendship}', [FriendshipController::class, 'destroy']);
    Route::get('/friends/search', [FriendshipController::class, 'search']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Route::get('/dashboard', [Admin\DashboardController::class, 'index']);
    // Route::resource('users', Admin\UserManagementController::class);
    // Route::resource('quizzes', Admin\QuizManagementController::class);
    // Route::get('/statistics', [Admin\StatisticsController::class, 'index']);
    // Route::post('/settings', [Admin\SystemSettingsController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| User Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->group(function () {
    // Route::get('/profile', [User\ProfileController::class, 'show']);
    // Route::put('/profile', [User\ProfileController::class, 'update']);
    // Route::resource('quizzes', User\QuizController::class);
    // Route::resource('friendships', User\FriendshipController::class);
    // Route::get('/game-history', [User\GameHistoryController::class, 'index']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/{userId}/quizzes', [QuizController::class, 'getUsersQuizzes']);
});

Route::get('images/{path}', [ImageController::class, 'getImage'])
    ->where('path', '.*')
    ->name('image.show');

/*
|--------------------------------------------------------------------------
| Guest/Public Routes
|--------------------------------------------------------------------------
*/
// Route::get('/quizzes', [Guest\PublicQuizController::class, 'index']);
// Route::get('/quizzes/{id}', [Guest\PublicQuizController::class, 'show']);
// Route::get('/leaderboard', [Guest\LeaderboardController::class, 'index']);

