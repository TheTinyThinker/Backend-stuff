<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        // You might want to customize this based on your specific needs
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

    /**
     * Get user's leaderboard entries.
     */
    public function leaderboardEntries()
    {
        return $this->hasMany(Leaderboard::class);
    }
}
