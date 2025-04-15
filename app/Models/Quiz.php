<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'img_url',
        'show_correct_answer',
        'is_public',
        'play_count',
        'correct_answer_percentage',
        'average_rating',
        'rating_count',
    ];

    protected $casts = [
        'show_correct_answer' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function getCreatedByAttribute()
    {
        return $this->user ? $this->user->name : 'Unknown';
    }

    public function getImageUrlAttribute() {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function ratings()
{
    return $this->hasMany(QuizRating::class);
}

}
