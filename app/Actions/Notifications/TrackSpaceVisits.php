<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\TracksSpaceVisits;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceVisit;
use App\Models\User;
use App\Support\NotificationAccess;
use Illuminate\Support\Carbon;

/**
 * The visits of spaces and `spaces.last_content_at` (#1476, ADR 0048). Chat messages never set `last_content_at`: a chat
 * space keeps its own cursor (`chat_reads`).
 */
final class TrackSpaceVisits implements TracksSpaceVisits
{
    final public function visit(User $user, Space $space): bool
    {
        return $this->write($user, $space, now());
    }

    final public function wroteIn(User $user, Space $space, bool $newContent = true): void
    {
        $now = now();

        if ($newContent) {
            // toBase: the update must not touch updated_at of the space
            Space::query()->whereKey($space->id)->toBase()->update(['last_content_at' => $now]);
            $space->setAttribute('last_content_at', $now);
        }

        // The author has seen what they wrote: the same instant, so that their own content leaves no dot
        $this->write($user, $space, $now);
    }

    final public function forget(User $user, Community $community): void
    {
        SpaceVisit::query()->where('user_id', $user->id)->whereIn('space_id', $community->spaces()->select('id'))->delete();
    }

    private function write(User $user, Space $space, Carbon $at): bool
    {
        $community = $space->community;
        if (! NotificationAccess::canView($user, $community, $space->loadMissing(['accessGroups', 'spaceGroup.accessGroups']))) {
            return false;
        }

        SpaceVisit::query()->upsert(
            [['user_id' => $user->id, 'space_id' => $space->id, 'visited_at' => $at]],
            ['user_id', 'space_id'],
            ['visited_at'],
        );

        return true;
    }
}
