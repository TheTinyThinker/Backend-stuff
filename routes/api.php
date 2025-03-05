<?php

use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

//HERE
use App\Http\Controllers\RoomController;
Route::post('send-test-broadcast', [RoomController::class, 'sendTestBroadcast']);



// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Public quiz routes - explicitly define these outside auth middleware
Route::get('quizzes', [QuizController::class, 'index']);
Route::get('quizzes/{id}', [QuizController::class, 'show']);






// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('logout', [AuthController::class, 'logout']);

    // Quiz system routes
    Route::apiResource('quizzes', QuizController::class)->except(['index', 'show']);
    Route::apiResource('questions', QuestionController::class);
    Route::apiResource('answers', AnswerController::class);
    Route::post('check-answer', [AnswerController::class, 'checkAnswer']);

    // Social routes
    Route::apiResource('friendships', FriendshipController::class);

    // Leaderboard routes
    Route::get('leaderboard', [LeaderboardController::class, 'index']);
    Route::get('user-rank/{user_id}', [LeaderboardController::class, 'userRank']);



});
