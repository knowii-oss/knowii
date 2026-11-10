<?php

namespace App\Http\Controllers\API;

use App\Contracts\Pages\TracksLessonProgress;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Page;
use App\Models\Space;
use App\Support\CourseProgress;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The progress of the current member in a course space (#1304, ADR 0034). Each answer is the progress of the whole
 * course: `total` lessons, `completed` lessons, their cuids in `completed_lessons`, and `next_lesson` (the first lesson
 * that is not done, null when every lesson is done).
 */
class LessonProgressApiController extends Controller
{
    use ApiResponses;

    /**
     * Get your progress in a course space.
     *
     * @throws AuthenticationException
     */
    final public function show(Request $request, Community $community, Space $space): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        Gate::authorize('trackProgress', $space);
        if ($space->type !== KnowiiSpaceType::Course) {
            throw new BusinessException('Only course spaces have a progress');
        }

        return self::success('Course progress', null, CourseProgress::for($user, $space));
    }

    /**
     * Mark a lesson as done. Marking it again changes nothing.
     *
     * @throws AuthenticationException
     */
    final public function store(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        app(TracksLessonProgress::class)->complete($user, $page);

        return self::success('Lesson marked as done', null, CourseProgress::for($user, $space));
    }

    /**
     * Mark a lesson as not done.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        app(TracksLessonProgress::class)->reopen($user, $page);

        return self::success('Lesson marked as not done', null, CourseProgress::for($user, $space));
    }
}
