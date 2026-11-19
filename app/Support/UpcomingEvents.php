<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The upcoming events of every events space of a community that a reader can read (#1513, #1514, ADR 0053): the list of
 * `GET communities/{community}/events`, and "Next events" on the home and on the public page of a community. Never a
 * draft, the next start first. A member gets the events spaces of their navigation; a visitor and a signed-in person
 * who is not a member get the public events spaces. The caller checks first that the reader may read the community
 * (a member), or that it has a public page (`PublicCommunity::ensureExists`).
 */
final class UpcomingEvents
{
    /**
     * The relations that the resource of an event of a list needs
     */
    public const array RELATIONS = ['host', 'cover'];

    /**
     * The events spaces that the reader can read, by id
     *
     * @param  Collection<int, SpaceGroup>|null  $tree  The navigation of a member, when the caller already has it
     * @return array<int, Space>
     */
    public static function spaces(Community $community, ?User $user, ?Collection $tree = null): array
    {
        if (PublicCommunity::isMember($user, $community)) {
            $spaces = ($tree ?? SpaceTree::for($community, $user))
                ->flatMap(static fn (SpaceGroup $group) => $group->spaces)
                ->filter(static fn (Space $space): bool => $space->type === KnowiiSpaceType::Events);
        } else {
            $spaces = $community->spaces()
                ->where('type', KnowiiSpaceType::Events->value)
                ->where('visibility', KnowiiSpaceVisibility::Public->value)
                ->get()
                ->filter(static fn (Space $space): bool => Gate::forUser($user)->check('view', $space));
        }

        /** @var array<int, Space> $byId */
        $byId = $spaces->keyBy('id')->all();

        return $byId;
    }

    /**
     * The upcoming events of these spaces, without the drafts, the next start first
     *
     * @param  array<int, Space>  $spaces
     * @return Builder<Event>
     */
    public static function query(Community $community, array $spaces): Builder
    {
        return Event::query()
            ->where('community_id', $community->id)
            ->whereIn('space_id', array_keys($spaces))
            ->where('state', '!=', KnowiiEventState::Draft->value)
            ->with(self::RELATIONS)
            ->upcoming();
    }

    /**
     * Give each event its space and its community: the policy and the resource of an event read them, and they are
     * known, so no query per event
     *
     * @param  Collection<int, Event>  $events
     * @param  array<int, Space>  $spaces
     * @return Collection<int, Event>
     */
    public static function inSpaces(Collection $events, Community $community, array $spaces): Collection
    {
        foreach ($events as $event) {
            $space = $spaces[$event->space_id];
            $space->setRelation('community', $community);
            $event->setRelation('space', $space);
            $event->setRelation('community', $community);
        }

        return $events;
    }

    /**
     * "Next events": the first upcoming events that the reader can read
     *
     * @param  Collection<int, SpaceGroup>|null  $tree  The navigation of a member, when the caller already has it
     * @return Collection<int, Event>
     */
    public static function next(Community $community, ?User $user, ?Collection $tree = null, int $limit = Constants::NEXT_EVENTS): Collection
    {
        $spaces = self::spaces($community, $user, $tree);
        if ($spaces === []) {
            return new Collection;
        }

        return self::inSpaces(self::query($community, $spaces)->limit($limit)->get(), $community, $spaces);
    }
}
