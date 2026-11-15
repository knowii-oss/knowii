<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\ChoosesSpaceLevels;
use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\NotificationSpaceLevel;
use App\Models\Space;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\NotificationAccess;
use App\Support\SpaceNotificationLevels;
use App\Support\SpaceTree;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The level of a member for a space (#1475, ADR 0042, design 2.2, 2.3). The most specific choice wins: the choice of the
 * member, then the default of the space that a manager set, then the built-in default (SpaceNotificationLevels).
 */
final class ChooseSpaceLevels implements ChoosesSpaceLevels
{
    final public function show(User $user, Space $space): array
    {
        $this->authorize($user, $space);

        return $this->present($user, $space);
    }

    final public function forCommunity(User $user, Community $community): array
    {
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community have a level for its spaces');
        }

        $spaces = SpaceTree::for($community, $user)
            ->flatMap(static fn ($group) => $group->spaces->map(static fn (Space $space): array => ['group' => $group->cuid, 'group_name' => $group->name, 'space' => $space]))
            ->filter(static fn (array $entry): bool => $entry['space']->type !== KnowiiSpaceType::Link)
            ->values();
        $chosen = NotificationSpaceLevel::query()
            ->where('user_id', $user->id)
            ->whereIn('space_id', $spaces->map(static fn (array $entry): int => $entry['space']->id)->all())
            ->get()
            ->keyBy('space_id');

        $levels = CommunityRoles::withKnownAdmins($community, [$user->id], static fn (): array => $spaces->map(static function (array $entry) use ($user, $community, $chosen): array {
            $space = $entry['space'];
            $default = SpaceNotificationLevels::defaultFor($user, $community, $space);
            $own = $chosen->get($space->id)?->level;

            return [
                'space_cuid' => $space->cuid,
                'space_name' => $space->name,
                'space_slug' => $space->slug,
                'space_type' => $space->type->value,
                'space_group_cuid' => $entry['group'],
                'space_group_name' => $entry['group_name'],
                'level' => ($own ?? $default)->value,
                'chosen' => $own !== null,
                'default' => $default->value,
            ];
        })->all());

        return array_values($levels);
    }

    final public function set(User $user, Space $space, array $input): array
    {
        $this->authorize($user, $space);

        /** @var array{level: string} $validated */
        $validated = Validator::make($input, ['level' => ['required', 'string', Rule::enum(KnowiiSpaceNotificationLevel::class)]])->validate();
        $level = KnowiiSpaceNotificationLevel::from($validated['level']);

        // "Everything" is a stream of new items: only a discussion space and a chat space have one
        if ($level === KnowiiSpaceNotificationLevel::All && ! in_array($space->type, [KnowiiSpaceType::Discussion, KnowiiSpaceType::Chat], true)) {
            throw new BusinessException('Everything only exists for discussion spaces and chat spaces');
        }

        NotificationSpaceLevel::query()->updateOrCreate(['user_id' => $user->id, 'space_id' => $space->id], ['level' => $level]);

        // Muted means nothing from there: the open rows of the space go (design 1.2.10)
        if ($level === KnowiiSpaceNotificationLevel::None) {
            app(PrunesNotifications::class)->forMutedSpace($user, $space);
        }

        return $this->present($user, $space);
    }

    final public function reset(User $user, Space $space): array
    {
        $this->authorize($user, $space);

        NotificationSpaceLevel::query()->where('user_id', $user->id)->where('space_id', $space->id)->delete();

        return $this->present($user, $space);
    }

    /**
     * @throws AuthorizationException
     * @throws BusinessException
     */
    private function authorize(User $user, Space $space): void
    {
        if ($space->type === KnowiiSpaceType::Link) {
            throw new BusinessException('A link space holds nothing to follow');
        }

        // Only a member is ever told, so only a member has a level (ADR 0044)
        if (! NotificationAccess::canView($user, $space->community, $space->loadMissing(['accessGroups', 'spaceGroup.accessGroups']))) {
            throw new AuthorizationException('Only the members who can view the space have a level for it');
        }
    }

    /**
     * @return array{space_cuid: string, level: string, chosen: bool, default: string}
     */
    private function present(User $user, Space $space): array
    {
        $default = SpaceNotificationLevels::defaultFor($user, $space->community, $space);
        $chosen = SpaceNotificationLevels::chosenBy($user, $space);

        return [
            'space_cuid' => $space->cuid,
            'level' => ($chosen ?? $default)->value,
            'chosen' => $chosen !== null,
            'default' => $default->value,
        ];
    }
}
