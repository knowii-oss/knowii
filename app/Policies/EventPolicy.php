<?php

namespace App\Policies;

use App\Enums\KnowiiEventLocationVisibility;
use App\Models\Event;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Who can read and change an event of an events space (#1513, ADR 0053). Creating an event is SpacePolicy::contribute:
 * the posting policy of the space decides. SpacePolicy::view stays the one place of the reading rule: this policy never
 * tests the access groups itself.
 *
 * "Host": the member named in `host_id`, while still a member of the community and able to read the space. A host who
 * left keeps `host_id` and loses every right of a host.
 */
class EventPolicy
{
    use HandlesAuthorization;

    /**
     * A draft is for its host, the owner and the admins. A published or cancelled event follows its space.
     */
    final public function view(?User $user, Event $event): bool
    {
        if ($event->isDraft()) {
            return $user !== null && (self::isHost($user, $event) || CommunityRoles::isOwnerOrAdmin($user, $event->community));
        }

        return Gate::forUser($user)->check('view', $event->space);
    }

    /**
     * Edit, publish, back to draft, restore and duplicate: the owner, an admin, or the host
     */
    final public function update(User $user, Event $event): bool
    {
        return CommunityRoles::isOwnerOrAdmin($user, $event->community) || self::isHost($user, $event);
    }

    /**
     * The people who change the event, and the moderators of the space (a moderator with the "delete content" right who
     * can read the space): they cancel the events of others that they can see, and do not edit them. A draft is not
     * theirs to see.
     */
    final public function cancel(User $user, Event $event): bool
    {
        return $this->update($user, $event)
            || (Gate::forUser($user)->check('moderate', $event->space) && $this->view($user, $event));
    }

    /**
     * The same people as for a cancel
     */
    final public function delete(User $user, Event $event): bool
    {
        return $this->cancel($user, $event);
    }

    /**
     * The meeting address and the place: never a visitor, never a person who is not a member. With `members`, every
     * member who can see the event; with `going`, the host, the owner and the admins.
     */
    final public function viewLocation(?User $user, Event $event): bool
    {
        if ($user === null || ! $user->belongsToCommunity($event->community) || ! $this->view($user, $event)) {
            return false;
        }

        return $event->location_visibility === KnowiiEventLocationVisibility::Members
            || CommunityRoles::isOwnerOrAdmin($user, $event->community)
            || self::isHost($user, $event);
    }

    /**
     * Whether the user is the host of the event and still holds the rights of a host
     */
    public static function isHost(User $user, Event $event): bool
    {
        return $event->host_id !== null
            && $event->host_id === $user->id
            && $user->belongsToCommunity($event->community)
            && Gate::forUser($user)->check('view', $event->space);
    }
}
