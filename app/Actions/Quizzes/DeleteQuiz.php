<?php

namespace App\Actions\Quizzes;

use App\Contracts\Quizzes\DeletesQuizzes;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Delete the quiz of a lesson (#1335): the managers of the course. Its questions, its choices and the results of the
 * members go by cascade. The lessons that members marked as done stay done.
 */
class DeleteQuiz implements DeletesQuizzes
{
    /**
     * @throws AuthorizationException
     */
    final public function delete(User $user, Quiz $quiz): void
    {
        Gate::forUser($user)->authorize('managePages', $quiz->page->space);

        $quiz->delete();

        Log::info('Quiz deleted', ['quiz' => $quiz->cuid]);
    }
}
