<?php

namespace App\Contracts\Pages;

use App\Models\Page;
use App\Models\User;

interface TracksLessonProgress
{
    /**
     * Mark a lesson of a course space as done for the user (#1304). Idempotent: a lesson already done stays done.
     */
    public function complete(User $user, Page $page): void;

    /**
     * Mark a lesson as not done. Idempotent.
     */
    public function reopen(User $user, Page $page): void;
}
