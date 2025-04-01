<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Create the table without foreign keys first
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->text('answer_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->integer('points')->default(1);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        // Then check if questions table exists before adding constraint
        if (Schema::hasTable('questions')) {
            Schema::table('answers', function (Blueprint $table) {
                $table->foreign('question_id')
                      ->references('id')
                      ->on('questions')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
        $table->dropForeign(['user_id']);
        $table->dropColumn('user_id');
    }
};
