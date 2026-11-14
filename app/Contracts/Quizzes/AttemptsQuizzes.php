<?php

namespace App\Contracts\Quizzes;

use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\User;

interface AttemptsQuizzes
{
    /**
     * Answer the quiz of a lesson: the server grades the answers and keeps the result of the member (#1335).
     *
     * @param  array<mixed>  $input
     */
    public function attempt(User $user, Quiz $quiz, array $input): QuizResult;
}
