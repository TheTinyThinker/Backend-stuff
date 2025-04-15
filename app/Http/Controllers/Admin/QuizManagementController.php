<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizManagementController extends Controller
{
    /**
     * Display a listing of quizzes.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Quiz::with('user:id,name');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Apply sorting without pagination
        $query->orderBy($request->sort_by ?? 'created_at', $request->sort_direction ?? 'desc');

        // Get all quizzes
        $quizzes = $query->get();

        // Format the response to include creator's name
        $formattedQuizzes = $quizzes->map(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });

        return response()->json($formattedQuizzes);
    }

    /**
     * Display the specified quiz with full details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $quiz = Quiz::with(['user:id,name', 'questions.answers'])
                    ->findOrFail($id);

        return response()->json($quiz);
    }

    /**
     * Update the specified quiz.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
            'is_public' => 'sometimes|boolean',
            'img_url' => 'sometimes|nullable|string',
        ]);

        $quiz->update($validated);

        return response()->json([
            'message' => 'Quiz updated successfully',
            'quiz' => $quiz
        ]);
    }

    /**
     * Remove the specified quiz.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $quiz = Quiz::findOrFail($id);

        // Use DB transaction to ensure all related records are deleted
        DB::beginTransaction();

        try {
            // Delete all answers related to this quiz's questions
            Answer::whereIn('question_id', function($query) use ($id) {
                $query->select('id')
                      ->from('questions')
                      ->where('quiz_id', $id);
            })->delete();

            // Delete all questions related to this quiz
            Question::where('quiz_id', $id)->delete();

            // Delete the quiz
            $quiz->delete();

            DB::commit();

            return response()->json([
                'message' => 'Quiz and all related content deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'message' => 'Failed to delete quiz: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Feature or unfeature a quiz
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function toggleFeatured(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $quiz->is_featured = !$quiz->is_featured;
        $quiz->save();

        return response()->json([
            'message' => $quiz->is_featured ? 'Quiz featured successfully' : 'Quiz unfeatured successfully',
            'is_featured' => $quiz->is_featured
        ]);
    }
}
