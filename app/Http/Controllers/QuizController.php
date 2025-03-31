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
use Illuminate\Support\Facades\Storage;

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
            'is_public' => 'boolean',
            'img_url' => 'nullable',
        ])->validate();

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'question_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);


        try {
            DB::beginTransaction();

            // Set default for show_correct_answer if not provided
            if (!isset($validatedData['show_correct_answer'])) {
                $validatedData['show_correct_answer'] = false;
            }

            // Set default for is_public if not provided
            if (!isset($validatedData['is_public'])) {
                $validatedData['is_public'] = true;
            }

            // Set user_id to authenticated user
            $validatedData['user_id'] = Auth::id();

            $quiz = Quiz::create($validatedData);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('/quiz_images');

                \Log::info('Stored file path:', ['path' => $path]);

                $validatedData['img_url'] = $path;
            }

            // Create a new quiz using validated data
            $quiz = Quiz::create($validatedData);

            // Create questions if provided
            if (isset($validatedData['questions']) && is_array($validatedData['questions'])) {
                foreach ($validatedData['questions'] as $index => $questionData) {
                    // Create the question

                    if ($request->hasFile("question_images.$index")) {
                        $questionImagePath = $request->file("question_images.$index")->store("/question_images");
                        $questionData['img_url'] = $questionImagePath;
                    }

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
                                'user_id' => Auth::id(), // user id fix
                            ]);
                        }
                    }
                }
            }
            // // Set default for is_public if not provided
            // if (!isset($validatedData['is_public'])) {
            //     $validatedData['is_public'] = true;
            // }

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
    public function show(string $id) //old show methed leaving hear for now remember to delete later when code is switched over to new public and private function
    {
        // Start with a base query
        $query = Quiz::with(['user:id,name', 'questions.answers']);

        // Apply privacy filter
        if (!Auth::check()) {
            // For guests: only show public quizzes
            $query->where('is_public', true);
        } else {
            // For logged-in users: show public quizzes OR their own private quizzes
            $query->where(function($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', Auth::id());
            });
        }

        // Now try to find the quiz with these restrictions
        $quiz = $query->find($id);

        // If quiz doesn't exist OR user doesn't have permission to see it
        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // User has access, so continue...
        $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
        $quiz->stats = [
            'play_count' => $quiz->play_count,
            'correct_answer_percentage' => round($quiz->correct_answer_percentage, 1) . '%',
            'average_rating' => round($quiz->average_rating, 1),
            'rating_count' => $quiz->rating_count
        ];

        return response()->json($quiz);
    }

    public function publicQuizzes()
    {
        $query = Quiz::with(['user:id,name'])
            ->where('is_public', true);

        $quizzes = $query->get();

        $formattedQuizzes = $quizzes->map(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });
        return response()->json($formattedQuizzes);

    }

    public function privateQuizzes(){

        if(!Auth::check()){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Quiz::with(['user:id,name'])
            ->where('is_public', false)
            ->where('user_id', Auth::id());

        $quizzes = $query->get();


        $formattedQuizzes = $quizzes->map(function ($quiz) {
            $quiz->created_by = $quiz->user ? $quiz->user->name : 'Unknown';
            return $quiz;
        });
        return response()->json($formattedQuizzes);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $quiz = Quiz::findOrFail($id);

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

                // Find questions that need to be deleted (i.e., those not in the incoming request)
                $questionsToDelete = $quiz->questions()->whereNotIn('id', $incomingQuestionIds)->get();

                // Delete those questions
                foreach ($questionsToDelete as $question) {
                    // Optionally, delete question images from storage before deleting the question
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
                            $userId = Auth::id() ?: $quiz->user_id; // Fallback to quiz creator if auth fails

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

    public function rateQuiz(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5'
        ]);

        $quiz = Quiz::findOrFail($id);
        $rating = $request->rating;

        // Store individual rating in quiz_ratings table
        DB::table('quiz_ratings')->updateOrInsert(
            ['user_id' => Auth::id(), 'quiz_id' => $id],
            ['rating' => $rating, 'updated_at' => now()]
        );

        // Update quiz average rating
        $oldRating = $quiz->average_rating;
        $oldCount = $quiz->rating_count;

        // If user already rated, don't increase count
        $userRatedBefore = DB::table('quiz_ratings')
            ->where('user_id', Auth::id())
            ->where('quiz_id', $id)
            ->exists();

        if (!$userRatedBefore) {
            $quiz->increment('rating_count');
            $newRating = (($oldRating * $oldCount) + $rating) / ($oldCount + 1);
        } else {
            $oldUserRating = DB::table('quiz_ratings')
                ->where('user_id', Auth::id())
                ->where('quiz_id', $id)
                ->value('rating');

            $newRating = (($oldRating * $oldCount) - $oldUserRating + $rating) / $oldCount;
        }

        $quiz->update(['average_rating' => $newRating]);

        return response()->json([
            'message' => 'Rating submitted successfully',
            'new_rating' => $newRating
        ]);
    }

}
