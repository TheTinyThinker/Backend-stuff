<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->integer('play_count')->default(0);
            $table->decimal('correct_answer_percentage', 5, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['play_count', 'correct_answer_percentage',
                               'average_rating', 'rating_count']);
        });
    }
};
