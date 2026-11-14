<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson quizzes (#1335, ADR 0038): a lesson of a course space has at most one quiz, with ordered questions and
 * choices. `quiz_results` keeps one row per member and quiz (best score, last score, last answers, number of
 * attempts, first pass): no history of attempts. Everything goes with the lesson, the space and the community
 * (cascades); a result also goes with the account of the member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // At most one quiz per lesson
            $table->foreignId('page_id')->unique()->constrained()->cascadeOnDelete();
            // Percent of correct answers needed to pass; 0 makes the quiz a self-check
            $table->unsignedSmallInteger('pass_mark')->default(70);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_pass_mark_percent CHECK (pass_mark BETWEEN 0 AND 100)');

        Schema::create('quiz_questions', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('kind');
            $table->text('prompt');
            $table->text('prompt_html');
            // Shown after the member answers
            $table->text('explanation')->nullable();
            $table->text('explanation_html')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'position']);
        });

        Schema::create('quiz_choices', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('label', 500);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['quiz_question_id', 'position']);
        });

        Schema::create('quiz_results', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            // To delete the results of a member who leaves the community in one statement
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('best_score');
            $table->unsignedSmallInteger('last_score');
            // The cuids of the chosen choices per question cuid, of the last attempt only
            $table->jsonb('last_answers');
            $table->unsignedInteger('attempts')->default(0);
            // The first time the member passed; null while they did not
            $table->timestamp('passed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
        Schema::dropIfExists('quiz_choices');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
