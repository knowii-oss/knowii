<?php

namespace App\Support;

use App\Enums\KnowiiFollowState;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The level of a member for a space (#1475, ADR 0042, design 2.2, 2.3): the most specific choice wins, the choice of the
 * member first, then the default of the space that a manager set, then the built-in default of the kind of space. The one
 * place that knows these rules.
 */
final class SpaceNotificationLevels
{
    /**
     * The built-in default: "Everything" for a discussion space where only the moderators post (the place where the
     * team speaks to everyone), "Only what is for me" for every other space. A chat space is never at "Everything" by
     * default: a member chooses it.
     */
    public static function builtInDefault(Space $space): KnowiiSpaceNotificationLevel
    {
        return $space->type === KnowiiSpaceType::Discussion && $space->posting_policy === KnowiiSpacePostingPolicy::Moderators
            ? KnowiiSpaceNotificationLevel::All
            : KnowiiSpaceNotificationLevel::Mine;
    }

    /**
     * The default of the space: what a manager chose, else the built-in default. A chat space never gets "Everything".
     */
    public static function defaultOf(Space $space): KnowiiSpaceNotificationLevel
    {
        $chosen = $space->default_notification_level;
        if ($chosen === null || ($chosen === KnowiiSpaceNotificationLevel::All && $space->type === KnowiiSpaceType::Chat)) {
            return self::builtInDefault($space);
        }

        return $chosen;
    }

    /**
     * The default of the space for one member: "Only what is for me" for an owner or an admin who sees a "groups" space
     * only because of the role, and not through an access group, whatever the default (design 2.3.2).
     */
    public static function defaultFor(User $user, Community $community, Space $space): KnowiiSpaceNotificationLevel
    {
        if ($space->visibility === KnowiiSpaceVisibility::Groups && CommunityRoles::isOwnerOrAdmin($user, $community) && ! $user->isInAccessGroupOf($space)) {
            return KnowiiSpaceNotificationLevel::Mine;
        }

        return self::defaultOf($space);
    }

    /**
     * The level that a member chose for the space, or null when they did not choose
     */
    public static function chosenBy(User $user, Space $space): ?KnowiiSpaceNotificationLevel
    {
        return NotificationSpaceLevel::query()->where('user_id', $user->id)->where('space_id', $space->id)->first()?->level;
    }

    /**
     * The level that applies to the member for the space
     */
    public static function levelOf(User $user, Community $community, Space $space): KnowiiSpaceNotificationLevel
    {
        return self::chosenBy($user, $space) ?? self::defaultFor($user, $community, $space);
    }

    /**
     * The level of many members for one space, by user id, with one query for all of them. The members need their access
     * groups and communities loaded (the default depends on them).
     *
     * @param  Collection<int, User>  $users
     * @return array<int, KnowiiSpaceNotificationLevel>
     */
    public static function levelsOf(Community $community, Space $space, Collection $users): array
    {
        $chosen = NotificationSpaceLevel::query()->where('space_id', $space->id)->whereIn('user_id', $users->modelKeys())->get()->keyBy('user_id');

        $levels = [];
        foreach ($users as $user) {
            $levels[$user->id] = $chosen->get($user->id)->level ?? self::defaultFor($user, $community, $space);
        }

        return $levels;
    }

    /**
     * The members, among the given ones, who muted the space or the thread: nothing reaches them from there, whatever the
     * kind (design 1.2.10). Two queries at most, for any number of members.
     *
     * @param  array{column: 'post_id'|'chat_message_id', id: int}|null  $thread
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public static function mutedAmong(?Space $space, ?array $thread, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $muted = [];
        if ($space !== null) {
            $muted = NotificationSpaceLevel::query()
                ->where('space_id', $space->id)
                ->where('level', KnowiiSpaceNotificationLevel::None->value)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->all();
        }
        if ($thread !== null) {
            $muted = [...$muted, ...NotificationFollow::query()
                ->where($thread['column'], $thread['id'])
                ->where('state', KnowiiFollowState::Muted->value)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->all()];
        }

        return array_values(array_unique(array_map(static fn ($id): int => is_numeric($id) ? (int) $id : 0, $muted)));
    }
}
