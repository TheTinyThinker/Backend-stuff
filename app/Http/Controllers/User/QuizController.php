<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuizController extends Controller
{
    /**
     * Display a listing of user's quizzes.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = Quiz::where('user_id', $userId);

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

        // Sorting
        $sortField = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $allowedSortFields = ['title', 'created_at', 'average_rating', 'rating_count'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $quizzes = $query->get();

        // Format the response to include creator's name
        $formattedQuizzes = $quizzes->map(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });

        return response()->json($formattedQuizzes);
    }

    /**
     * Store a newly created quiz.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate incoming data
        $quizData = json_decode($request->quiz, true);

        $validatedData = \Validator::make($quizData, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'img_url' => 'nullable|string|max:2048',
            'show_correct_answer' => 'boolean',
            'is_public' => 'boolean',
            'questions' => 'sometimes|array',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|string|in:single choice,multiple choice',
            'questions.*.difficulty' => 'nullable|string',
            'questions.*.img_url' => 'nullable|string',
            'questions.*.time_to_answer' => 'nullable|integer',
            'questions.*.answer_options' => 'required_with:questions|array|min:1',
            'questions.*.answer_options.*.answer_text' => 'required|string',
            'questions.*.answer_options.*.is_correct' => 'required|boolean',
        ])->validate();

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'question_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Set default values
            if (!isset($validatedData['show_correct_answer'])) {
                $validatedData['show_correct_answer'] = false;
            }

            if (!isset($validatedData['is_public'])) {
                $validatedData['is_public'] = true;
            }

            // Set user_id to authenticated user
            $validatedData['user_id'] = Auth::id();

            // Handle quiz image
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('/quiz_images');
                $validatedData['img_url'] = $path;
            }

            // Create a new quiz
            $quiz = Quiz::create($validatedData);

            // Create questions if provided
            if (isset($validatedData['questions']) && is_array($validatedData['questions'])) {
                foreach ($validatedData['questions'] as $index => $questionData) {
                    // Handle question image
                    if ($request->hasFile("question_images.$index")) {
                        $questionImagePath = $request->file("question_images.$index")->store("/question_images");
                        $questionData['img_url'] = $questionImagePath;
                    }

                    // Create question
                    $question = $quiz->questions()->create([
                        'question_text' => $questionData['question_text'],
                        'question_type' => $questionData['question_type'],
                        'difficulty' => $questionData['difficulty'] ?? null,
                        'img_url' => $questionData['img_url'] ?? null,
                        'time_to_answer' => $questionData['time_to_answer'] ?? 30,
                    ]);

                    // Create answers
                    if (isset($questionData['answer_options'])) {
                        foreach ($questionData['answer_options'] as $answerData) {
                            $question->answers()->create([
                                'answer_text' => $answerData['answer_text'],
                                'is_correct' => $answerData['is_correct'],
                                'user_id' => Auth::id(),
                            ]);
                        }
                    }
                }
            }

            DB::commit();

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
     * Display the specified quiz.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::id();

        $quiz = Quiz::where('id', $id)
            ->where('user_id', $userId)
            ->with(['questions.answers'])
            ->firstOrFail();

        $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
        $quiz->stats = [
            'play_count' => $quiz->play_count ?? 0,
            'correct_answer_percentage' => round($quiz->correct_answer_percentage ?? 0, 1) . '%',
            'average_rating' => round($quiz->average_rating ?? 0, 1),
            'rating_count' => $quiz->rating_count ?? 0
        ];

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
        // Find quiz and verify ownership
        $userId = Auth::id();
        $quiz = Quiz::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Decode JSON payload
        $quizData = json_decode($request->quiz, true);

        // Validate request
        $validatedData = \Validator::make($quizData, [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'img_url' => 'nullable|string|max:2048',
            'show_correct_answer' => 'boolean',
            'is_public' => 'boolean',
            'questions' => 'sometimes|array',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|string|in:single choice,multiple choice',
            'questions.*.difficulty' => 'nullable|string',
            'questions.*.img_url' => 'nullable|string',
            'questions.*.time_to_answer' => 'nullable|integer',
            'questions.*.answer_options' => 'required_with:questions|array|min:1',
            'questions.*.answer_options.*.answer_text' => 'required|string',
            'questions.*.answer_options.*.is_correct' => 'required|boolean',
        ])->validate();

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'question_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Handle quiz image update
            if ($request->hasFile('image')) {
                if ($quiz->img_url) {
                    Storage::delete($quiz->img_url);
                }
                $path = $request->file('image')->store('/quiz_images');
                $validatedData['img_url'] = $path;
            }

            // Update quiz details
            $quiz->update($validatedData);

            // Update or create questions
            if (isset($validatedData['questions']) && is_array($validatedData['questions'])) {
                // Collect IDs of incoming questions
                $incomingQuestionIds = collect($validatedData['questions'])->pluck('id')->filter();

                // Find questions that need to be deleted
                $questionsToDelete = $quiz->questions()->whereNotIn('id', $incomingQuestionIds)->get();

                // Delete those questions
                foreach ($questionsToDelete as $question) {
                    if ($question->img_url) {
                        Storage::delete($question->img_url);
                    }
                    $question->delete();
                }

                // Process each incoming question
                foreach ($validatedData['questions'] as $index => $questionData) {
                    // Create or update the question
                    $question = Question::updateOrCreate(
                        ['quiz_id' => $quiz->id, 'id' => $questionData['id'] ?? null],
                        [
                            'question_text' => $questionData['question_text'],
                            'question_type' => $questionData['question_type'],
                            'difficulty' => $questionData['difficulty'] ?? null,
                            'img_url' => $questionData['img_url'] ?? null,
                            'time_to_answer' => $questionData['time_to_answer'] ?? 30,
                        ]
                    );

                    // Handle question images
                    if ($request->hasFile("question_images.$index")) {
                        if ($question->img_url) {
                            Storage::delete($question->img_url);
                        }
                        $questionImagePath = $request->file("question_images.$index")->store("/question_images");
                        $question->update(['img_url' => $questionImagePath]);
                    }

                    // Sync answers
                    if (isset($questionData['answer_options'])) {
                        $question->answers()->delete(); // Remove old answers
                        foreach ($questionData['answer_options'] as $answerData) {
                            $question->answers()->create([
                                'answer_text' => $answerData['answer_text'],
                                'is_correct' => $answerData['is_correct'],
                                'user_id' => $userId,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json($quiz->load(['questions.answers']), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update quiz: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified quiz.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::id();

        $quiz = Quiz::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Delete quiz and all related files/data
            if ($quiz->img_url) {
                Storage::delete($quiz->img_url);
            }

            // Delete question images
            foreach ($quiz->questions as $question) {
                if ($question->img_url) {
                    Storage::delete($question->img_url);
                }
                $question->delete();
            }

            // Delete the quiz
            $quiz->delete();

            DB::commit();

            return response()->json([
                'message' => 'Quiz deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete quiz: ' . $e->getMessage()
            ], 500);
        }
    }
}
