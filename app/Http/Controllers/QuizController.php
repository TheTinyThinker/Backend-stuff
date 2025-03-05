<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Initialize the query builder first
        $query = Quiz::with('user:id,name');  // Don't call get() yet!

        // Filter based on authentication status
        if (!Auth::check()) {
            // Only show public quizzes to guests
            $query->where('is_public', true);
        } else {
            // Show public quizzes or private quizzes owned by the current user
            $query->where(function ($q) {
                $q->where('is_public', true)
                    ->orWhere('user_id', Auth::id());
            });
        }

        // Execute the query after applying all filters
        $quizzes = $query->get();

        // Format the response to include creator's name more clearly
        $formattedQuizzes = $quizzes->map(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });

        // Return a response with the list of quizzes including user data
        return response()->json($formattedQuizzes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate incoming data
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'img_url' => 'nullable|string|max:2048',
            'show_correct_answer' => 'boolean',
            'is_public' => 'boolean',  // Add this line
            'questions' => 'sometimes|array',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|string|in:single choice,multiple choice',
            'questions.*.difficulty' => 'nullable|string',
            'questions.*.img_url' => 'nullable|string',
            'questions.*.time_to_answer' => 'nullable|integer',
            'questions.*.answer_options' => 'required_with:questions|array|min:1',
            'questions.*.answer_options.*.answer_text' => 'required|string',
            'questions.*.answer_options.*.is_correct' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Set default for show_correct_answer if not provided
            if (!isset($validatedData['show_correct_answer'])) {
                $validatedData['show_correct_answer'] = false;
            }

            // Set user_id to authenticated user
            $validatedData['user_id'] = Auth::id();

            // Create a new quiz using validated data
            $quiz = Quiz::create($validatedData);

            // Create questions if provided
            if (isset($validatedData['questions']) && is_array($validatedData['questions'])) {
                foreach ($validatedData['questions'] as $questionData) {
                    // Create the question
                    $question = $quiz->questions()->create([
                        'question_text' => $questionData['question_text'],
                        'question_type' => $questionData['question_type'],
                        'difficulty' => $questionData['difficulty'] ?? null,
                        'img_url' => $questionData['img_url'] ?? null,
                        'time_to_answer' => $questionData['time_to_answer'] ?? 30,
                    ]);

                    // Create answer options for this question
                    if (isset($questionData['answer_options'])) {
                        foreach ($questionData['answer_options'] as $answerData) {
                            $question->answers()->create([
                                'answer_text' => $answerData['answer_text'],
                                'is_correct' => $answerData['is_correct'],
                            ]);
                        }
                    }
                }
            }
            // Set default for is_public if not provided
            if (!isset($validatedData['is_public'])) {
                $validatedData['is_public'] = true;
            }

            DB::commit();

            // Return the created quiz with questions and answers
            return response()->json(
                $quiz->load(['questions.answers']),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create quiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find the quiz by its ID with creator info and questions
        $quiz = Quiz::with(['user:id,name', 'questions.answers'])->findOrFail($id);

        // Add created_by field for clearer display
        $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';

        // Return a response with the quiz data
        return response()->json($quiz);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Find the quiz by its ID
        $quiz = Quiz::findOrFail($id);

        // Check if user is authorized to update this quiz
        if (Auth::id() != $quiz->user_id) {
            return response()->json(['message' => 'You are not authorized to update this quiz'], 403);
        }

        // Validate incoming data
        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'img_url' => 'nullable|string|max:2048',
            'show_correct_answer' => 'boolean',
            'is_public' => 'boolean',  // Add this line
        ]);

        // Update the quiz with new data
        $quiz->update($validatedData);

        // Return a response with the updated quiz
        return response()->json($quiz);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Find the quiz by its ID
        $quiz = Quiz::findOrFail($id);

        // Check if user is authorized to delete this quiz
        if (Auth::id() != $quiz->user_id) {
            return response()->json(['message' => 'You are not authorized to delete this quiz'], 403);
        }

        // Delete the quiz
        $quiz->delete();

        // Return a response indicating that the quiz was deleted
        return response()->json(['message' => 'Quiz deleted successfully']);
    }
}
