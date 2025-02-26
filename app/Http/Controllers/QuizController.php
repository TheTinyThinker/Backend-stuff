<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuizController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all quizzes from the database
        $quizzes = Quiz::all();

        // Return a response with the list of quizzes
        return response()->json($quizzes);
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
        ]);

        // Create a new quiz using validated data
        $quiz = Quiz::create($validatedData);

        // Return a response with the created quiz
        return response()->json($quiz, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Find the quiz by its ID
        $quiz = Quiz::findOrFail($id);

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

        // Validate incoming data
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
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

        // Delete the quiz
        $quiz->delete();

        // Return a response indicating that the quiz was deleted
        return response()->json(['message' => 'Quiz deleted successfully']);
    }
}
