<?php

namespace App\Actions\Pages;

use App\Contracts\Pages\TracksLessonProgress;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\LessonProgress;
use App\Models\Page;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Course progress per member (#1304, ADR 0034). It changes only the member's own data, so reading the course is enough
 * (`pages:read`), but only members of the community track it: SpacePolicy::trackProgress.
 */
class TrackLessonProgress implements TracksLessonProgress
{
    /**
     * @throws AuthorizationException
     * @throws BusinessException
     */
    final public function complete(User $user, Page $page): void
    {
        $this->authorize($user, $page);

        LessonProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'page_id' => $page->id],
            ['community_id' => $page->space->community_id, 'space_id' => $page->space_id, 'completed_at' => now()],
        );
    }

    /**
     * @throws AuthorizationException
     * @throws BusinessException
     */
    final public function reopen(User $user, Page $page): void
    {
        $this->authorize($user, $page);

        LessonProgress::query()->where('user_id', $user->id)->where('page_id', $page->id)->delete();
    }

    /**
     * @throws AuthorizationException
     * @throws BusinessException
     */
    private function authorize(User $user, Page $page): void
    {
        Gate::forUser($user)->authorize('trackProgress', $page->space);

        if ($page->space->type !== KnowiiSpaceType::Course || $page->isSection()) {
            throw new BusinessException('Only the lessons of a course space can be marked as done');
        }
    }
}
