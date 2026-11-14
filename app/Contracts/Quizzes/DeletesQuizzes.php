<?php

namespace App\Contracts\Quizzes;

use App\Models\Quiz;
use App\Models\User;

interface DeletesQuizzes
{
    /**
     * Delete the quiz of a lesson, with the results of the members (#1335).
     */
    public function delete(User $user, Quiz $quiz): void;
}
