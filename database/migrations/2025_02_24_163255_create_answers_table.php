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
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->text('answer_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->integer('points')->default(1);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });
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
