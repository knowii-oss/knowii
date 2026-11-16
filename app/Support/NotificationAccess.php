<?php

namespace App\Support;

use App\Enums\KnowiiSpaceVisibility;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The access rule of the notifications (#1474, ADR 0042, design 6.8): a member is told about an item only while they
 * can view it. The rule is checked when a row is written, when the list and the count are read, and when the access
 * groups, the members or the visibility of a space change. A row that fails the check is deleted.
 *
 * "Can view" is the community membership plus SpacePolicy::view, the one place that knows the rule (ADR 0025, 0041).
 */
final class NotificationAccess
{
    /**
     * Whether the member can view what happens in the space of the community. A null space (an item without a space)
     * needs the membership only.
     */
    public static function canView(User $user, Community $community, ?Space $space): bool
    {
        // A public space is readable by everyone, but only a member of the community is told about it
        if (! $user->belongsToCommunity($community)) {
            return false;
        }

        if ($space === null) {
            return true;
        }

        $space->setRelation('community', $community);

        return Gate::forUser($user)->allows('view', $space);
    }

    /**
     * The spaces of the community that the member can view, by id (#1476): the reading rule of SpacePolicy::view for each
     * space, with the spaces, their access groups and their space groups loaded at once and the role of the member read once
     * (no query per space).
     *
     * @return Collection<int, Space>
     */
    public static function viewableSpaces(User $user, Community $community): Collection
    {
        $spaces = Space::query()->with(['accessGroups', 'spaceGroup.accessGroups'])->where('community_id', $community->id)->get();

        return CommunityRoles::withKnownAdmins(
            $community,
            [$user->id],
            static fn (): Collection => $spaces->filter(static fn (Space $space): bool => self::canView($user, $community, $space))->keyBy('id'),
        );
    }

    /**
     * The members, among the given ones, who can view what happens in the space of the community. The members need their
     * communities and their access groups loaded, and the space its access groups and its space group: the check then
     * runs no query per member (one query for the roles of all the members of a "groups" space).
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public static function viewers(Collection $users, Community $community, ?Space $space): Collection
    {
        $ids = array_values(array_map(static fn ($id): int => is_numeric($id) ? (int) $id : 0, $users->modelKeys()));

        return CommunityRoles::withKnownAdmins($community, $ids, static fn () => $users->filter(static fn (User $user): bool => self::canView($user, $community, $space))->values());
    }

    /**
     * Delete the follows and the space levels of the members who cannot view their space any more (#1475, ADR 0042): the
     * "groups" spaces of the community, or one space. The choices of a member who left the community go with
     * PrunesNotifications::forMember. The members, the communities and the spaces load once per chunk of rows.
     *
     * @return int The number of rows deleted
     */
    public static function pruneChoices(Community $community, ?Space $space = null): int
    {
        $spaces = Space::query()
            ->with(['accessGroups', 'spaceGroup.accessGroups'])
            ->where('community_id', $community->id)
            ->when($space !== null, static fn (Builder $query) => $query->whereKey($space?->id), static fn (Builder $query) => $query->where('visibility', KnowiiSpaceVisibility::Groups->value))
            ->get()
            ->keyBy('id');
        if ($spaces->isEmpty()) {
            return 0;
        }

        $deleted = 0;

        NotificationSpaceLevel::query()->whereIn('space_id', $spaces->modelKeys())->chunkById(500, static function (Collection $levels) use ($community, $spaces, &$deleted): void {
            $users = User::query()->with(['communities', 'accessGroups'])->whereIn('id', $levels->pluck('user_id')->unique()->values()->all())->get()->keyBy('id');
            $doomed = $levels->filter(static function (NotificationSpaceLevel $level) use ($community, $spaces, $users): bool {
                $user = $users->get($level->user_id);

                return $user !== null && ! self::canView($user, $community, $spaces->get($level->space_id));
            });
            NotificationSpaceLevel::query()->whereIn('id', $doomed->modelKeys())->delete();
            $deleted += $doomed->count();
        });

        $inSpaces = static fn (Builder $query): Builder => $query
            ->whereIn('post_id', Post::query()->whereIn('space_id', $spaces->modelKeys())->select('id'))
            ->orWhereIn('chat_message_id', ChatMessage::query()->whereIn('space_id', $spaces->modelKeys())->select('id'));
        NotificationFollow::query()->where($inSpaces)->chunkById(500, static function (Collection $follows) use ($community, $spaces, &$deleted): void {
            $users = User::query()->with(['communities', 'accessGroups'])->whereIn('id', $follows->pluck('user_id')->unique()->values()->all())->get()->keyBy('id');
            $postSpaces = Post::query()->whereIn('id', $follows->pluck('post_id')->filter()->values()->all())->pluck('space_id', 'id');
            $messageSpaces = ChatMessage::query()->whereIn('id', $follows->pluck('chat_message_id')->filter()->values()->all())->pluck('space_id', 'id');
            $doomed = $follows->filter(static function (NotificationFollow $follow) use ($community, $spaces, $users, $postSpaces, $messageSpaces): bool {
                $user = $users->get($follow->user_id);
                $spaceId = $follow->post_id !== null ? $postSpaces->get($follow->post_id) : $messageSpaces->get($follow->chat_message_id);

                return $user !== null && is_numeric($spaceId) && ! self::canView($user, $community, $spaces->get((int) $spaceId));
            });
            NotificationFollow::query()->whereIn('id', $doomed->modelKeys())->delete();
            $deleted += $doomed->count();
        });

        return $deleted;
    }

    /**
     * Delete the rows of the query whose recipient cannot view their item any more, and tell the recipients. The
     * members, the communities and the spaces load once for all the rows (no query per row).
     *
     * @param  Builder<Notification>  $rows
     * @return int The number of rows deleted
     */
    public static function prune(Builder $rows): int
    {
        /** @var Collection<int, Notification> $candidates */
        $candidates = (clone $rows)->get(['notifications.id', 'notifications.cuid', 'notifications.user_id', 'notifications.community_id', 'notifications.space_id']);
        if ($candidates->isEmpty()) {
            return 0;
        }

        $spaces = Space::query()
            ->with(['accessGroups', 'spaceGroup.accessGroups'])
            ->whereIn('id', $candidates->pluck('space_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $communities = Community::query()
            ->whereIn('id', $candidates->pluck('community_id')->merge($spaces->pluck('community_id'))->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $users = User::query()->with(['communities', 'accessGroups'])->whereIn('id', $candidates->pluck('user_id')->unique()->values()->all())->get()->keyBy('id');

        $doomed = $candidates->filter(function (Notification $row) use ($spaces, $communities, $users): bool {
            $user = $users->get($row->user_id);
            $community = $communities->get($row->community_id);
            if ($user === null || $community === null) {
                return false;
            }
            $space = $row->space_id === null ? null : $spaces->get($row->space_id);

            return ! self::canView($user, $community, $space);
        });
        if ($doomed->isEmpty()) {
            return 0;
        }

        Notification::query()->whereIn('id', $doomed->modelKeys())->delete();
        NotificationEvents::changed($doomed);

        return $doomed->count();
    }
}
