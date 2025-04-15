<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizRating extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_id', 'user_id', 'rating'];

    /**
     * Get the quiz that was rated.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get the user who made the rating.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
