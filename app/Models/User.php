<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'total_score',
        'correct_answers',
        'incorrect_answers',
        'correct_percentage',
        'total_questions_answered',
        'total_quizzes_attempted',
        'highest_score',
        'average_score'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get quizzes created by this user.
     */
    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }


        // Total Score Calculation
     public function getTotalScoreAttribute()
    {
        return $this->answers()->where('is_correct', true)->sum('points');
    }

     // Percentage of Correct Answers
    public function getCorrectPercentageAttribute()
    {
        $totalAnswers = $this->answers()->count();
        $correctAnswers = $this->answers()->where('is_correct', true)->count();

        if ($totalAnswers === 0) {
            return 0; // Avoid division by zero
        }

        return round(($correctAnswers / $totalAnswers) * 100, 2);
    }




    /**
     * Get user's friendships where they initiated the request.
     */
    public function sentFriendships()
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    /**
     * Get user's friendships where they received the request.
     */
    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    /**
     * Get all friends (both directions).
     */
    public function friends()
    {
        // This is a more complex relationship that combines sent and received friendships

        return $this->hasManyThrough(
            User::class,
            Friendship::class,
            'user_id', // Foreign key on friendships table
            'id', // Foreign key on users table
            'id', // Local key on users table
            'friend_id' // Local key on friendships table
        )->where('status', 'accepted')
        ->union(
            $this->hasManyThrough(
                User::class,
                Friendship::class,
                'friend_id', // Foreign key on friendships table
                'id', // Foreign key on users table
                'id', // Local key on users table
                'user_id' // Local key on friendships table
            )->where('status', 'accepted')
        );
    }

    public function friendships()
{
    return $this->hasMany(Friendship::class, 'user_id')
        ->orWhere('friend_id', $this->id);
}

    /**
     * Get user's leaderboard entries.
     */
    public function leaderboardEntries()
    {
        return $this->hasMany(Leaderboard::class);
    }

    public function getProfilePictureUrlAttribute()
{
    return $this->profile_picture
        ? asset('storage/' . $this->profile_picture)
        : asset('images/default-profile.png');
}

public function getCorrectAnswersAttribute()
    {
        return $this->answers()->where('is_correct', true)->count();
    }

    // Get count of incorrect answers
    public function getIncorrectAnswersAttribute()
    {
        return $this->answers()->where('is_correct', false)->count();
    }

    // Get total questions answered
    public function getTotalQuestionsAnsweredAttribute()
    {
        return $this->answers()->count();
    }

    // Get total quizzes attempted (through leaderboard entries)
    public function getTotalQuizzesAttemptedAttribute()
    {
        return $this->leaderboards()->distinct('quiz_id')->count();
    }

    // Get highest quiz score
    public function getHighestScoreAttribute()
    {
        return $this->leaderboards()->max('points') ?? 0;
    }

    // Get average score per quiz
    public function getAverageScoreAttribute()
    {
        return $this->leaderboards()->avg('points') ?? 0;
    }

    // // Relationships
    // public function answers()
    // {
    //     return $this->hasMany(Answer::class);
    // }

    public function leaderboards()
    {
        return $this->hasMany(Leaderboard::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole($roleName)
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

}
