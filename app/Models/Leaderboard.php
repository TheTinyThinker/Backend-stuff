<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'quiz_id', 'points'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}

