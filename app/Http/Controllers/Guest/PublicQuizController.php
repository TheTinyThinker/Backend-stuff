<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;

class PublicQuizController extends Controller
{
    /**
     * Display a listing of public quizzes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Quiz::with('user:id,name')
            ->where('is_public', true);

        // Apply search filter if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply category filter if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Sort options
        $sortField = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $allowedSortFields = ['title', 'created_at', 'average_rating', 'rating_count'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get featured quizzes first if requested
        if ($request->has('featured') && $request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Get all results instead of paginating
        $quizzes = $query->get();

        // Format the response to include creator's name
        $formattedQuizzes = $quizzes->map(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });

        return response()->json($formattedQuizzes);
    }

    /**
     * Display the specified public quiz.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $quiz = Quiz::with(['user:id,name'])
            ->where('is_public', true)
            ->findOrFail($id);

        // For public view, don't include answers marked as correct
        $quiz->load(['questions' => function($query) {
            $query->select('id', 'quiz_id', 'question_text', 'question_type', 'time_limit');
        }]);

        // Get answers without correct/incorrect flag
        $questions = $quiz->questions;
        foreach ($questions as $question) {
            $question->answers = $question->answers()
                ->select('id', 'question_id', 'answer_text')
                ->get();
        }

        $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';

        return response()->json($quiz);
    }

    /**
     * Get top rated quizzes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function topRated()
    {
        $quizzes = Quiz::with('user:id,name')
            ->where('is_public', true)
            ->where('rating_count', '>', 5) // At least 5 ratings
            ->orderBy('average_rating', 'desc')
            ->take(10)
            ->get();

        $quizzes->transform(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });

        return response()->json($quizzes);
    }

    /**
     * Get featured quizzes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function featured()
    {
        $quizzes = Quiz::with('user:id,name')
            ->where('is_public', true)
            ->where('is_featured', true)
            ->take(6)
            ->get();

        $quizzes->transform(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });

        return response()->json($quizzes);
    }

    /**
     * Get list of available categories
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories()
    {
        $categories = Quiz::select('category')
            ->where('is_public', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        return response()->json($categories);
    }
}
