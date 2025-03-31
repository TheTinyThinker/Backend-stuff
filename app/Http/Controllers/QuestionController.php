<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    /**
     * Display a listing of the questions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Question::query();

        // Filter by quiz_id if provided
        if ($request->has('quiz_id')) {
            $query->where('quiz_id', $request->quiz_id);
        }

        return $query->with('answers')->get();
    }

    /**
     * Store a newly created question in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question_text' => 'required|string',
            'question_type' => 'required|string|in:single choice,multiple choice',
            'difficulty' => 'nullable|string',
            'img_url' => 'nullable|string',
            'time_to_answer' => 'nullable|integer',
            'answer_options' => 'required|array|min:1',
            'answer_options.*.answer_text' => 'required|string',
            'answer_options.*.is_correct' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Create question
            $question = Question::create([
                'quiz_id' => $request->quiz_id,
                'question_text' => $request->question_text,
                'question_type' => $request->question_type,
                'difficulty' => $request->difficulty,
                'img_url' => $request->img_url,
                'time_to_answer' => $request->time_to_answer,
            ]);

            // Create answer options
            foreach ($request->answer_options as $option) {
                $answer = new Answer([
                    'answer_text' => $option['answer_text'],
                    'is_correct' => $option['is_correct'],
                    'user_id' => Auth::id(),
                ]);
                $question->answers()->save($answer);
            }

            DB::commit();

            return $question->load('answers');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create question: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified question.
     *
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function show(Question $question)
    {
        return $question->load('answers');
    }

    /**
     * Update the specified question in the database.
     *
        @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Question $question)
    {
        $request->validate([
            'quiz_id' => 'sometimes|required|exists:quizzes,id',
            'question_text' => 'sometimes|required|string',
            'question_type' => 'sometimes|required|string|in:single choice,multiple choice',
            'difficulty' => 'nullable|string',
            'img_url' => 'nullable|string',
            'time_to_answer' => 'nullable|integer',
            'answer_options' => 'sometimes|required|array|min:1',
            'answer_options.*.id' => 'nullable|exists:answers,id',
            'answer_options.*.answer_text' => 'required|string',
            'answer_options.*.is_correct' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            $question->update([
                'quiz_id' => $request->quiz_id ?? $question->quiz_id,
                'question_text' => $request->question_text ?? $question->question_text,
                'question_type' => $request->question_type ?? $question->question_type,
                'difficulty' => $request->difficulty ?? $question->difficulty,
                'img_url' => $request->img_url ?? $question->img_url,
                'time_to_answer' => $request->time_to_answer ?? $question->time_to_answer,
            ]);

            // Update answer options if provided
            if ($request->has('answer_options')) {
                // Remove existing answers not in the update
                $existingIds = collect($request->answer_options)
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                $question->answers()
                    ->whereNotIn('id', $existingIds)
                    ->delete();

                // Update or create answers
                foreach ($request->answer_options as $option) {
                    if (!empty($option['id'])) {
                        $answer = Answer::find($option['id']);
                        if ($answer) {
                            $answer->update([
                                'answer_text' => $option['answer_text'],
                                'is_correct' => $option['is_correct'],
                                'user_id' => Auth::id(),
                            ]);
                        }
                    } else {
                        $answer = new Answer([
                            'answer_text' => $option['answer_text'],
                            'is_correct' => $option['is_correct'],
                            'user_id' => Auth::id(),
                        ]);
                        $question->answers()->save($answer);
                    }
                }
            }

            DB::commit();

            return $question->load('answers');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update question: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified question from the database.
     *
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function destroy(Question $question)
    {
        try {
            DB::beginTransaction();
            // Delete associated answers
            $question->answers()->delete();
            // Delete the question
            $question->delete();
            DB::commit();

            return response()->json(['message' => 'Question deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete question: ' . $e->getMessage()], 500);
        }
    }
}
