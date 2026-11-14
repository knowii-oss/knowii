<?php

namespace App\Contracts\Quizzes;

use App\Models\Page;
use App\Models\Quiz;
use App\Models\User;

interface SavesQuizzes
{
    /**
     * Create the quiz of a lesson, or replace it as a whole: the pass mark and every question (#1335).
     *
     * @param  array<mixed>  $input
     */
    public function save(User $user, Page $page, array $input): Quiz;
}
