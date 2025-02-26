<?php

use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
Route::post('check-answer', [AnswerController::class, 'checkAnswer'])->middleware('auth:sanctum');
Route::get('leaderboard', [LeaderboardController::class, 'index'])->middleware('auth:sanctum');
Route::get('user-rank/{user_id}', [LeaderboardController::class, 'userRank'])->middleware('auth:sanctum');



Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('quizzes', QuizController::class);
    Route::apiResource('questions', QuestionController::class);
    Route::apiResource('answers', AnswerController::class);
    Route::apiResource('friendships', FriendshipController::class);
    Route::get('leaderboard', [LeaderboardController::class, 'index']);
});

