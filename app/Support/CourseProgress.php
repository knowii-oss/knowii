<?php

namespace App\Support;

use App\Models\LessonProgress;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;

/**
 * The progress of a member in a course space (#1304, ADR 0034): how many lessons they marked as done, which ones,
 * and where to continue (the first lesson in the order that is not done). Sections are headings, not lessons.
 */
final class CourseProgress
{
    /**
     * @return array{total: int, completed: int, completed_lessons: list<string>, next_lesson: string|null}
     */
    public static function for(User $user, Space $space): array
    {
        $lessons = $space->pages()->get(['id', 'cuid', 'kind', 'position'])->reject(static fn (Page $page) => $page->isSection())->values();
        $done = LessonProgress::query()->where('user_id', $user->id)->where('space_id', $space->id)->pluck('page_id')->flip();

        $completed = $lessons->filter(static fn (Page $page) => $done->has($page->id));
        $next = $lessons->first(static fn (Page $page) => ! $done->has($page->id));

        return [
            'total' => $lessons->count(),
            'completed' => $completed->count(),
            'completed_lessons' => array_values($completed->map(static fn (Page $page): string => $page->cuid)->all()),
            'next_lesson' => $next?->cuid,
        ];
    }
}
