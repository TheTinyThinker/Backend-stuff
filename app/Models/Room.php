<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    // Specify the table name (optional if it follows Laravel naming conventions)
    protected $table = 'rooms';

    // Define fillable fields to allow mass assignment
    protected $fillable = ['code'];

    // Disable timestamps if not needed
    public $timestamps = true; // Set to false if you don’t want created_at/updated_at

    /**
     * Relationship Example (if rooms belong to a user or game)
     */
    // public function user() {
    //     return $this->belongsTo(User::class);
    // }

    // public function game() {
    //     return $this->hasOne(Game::class);
    // }
}

