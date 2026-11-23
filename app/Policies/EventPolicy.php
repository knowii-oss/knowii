<?php

namespace App\Policies;

use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
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
     * member who can see the event; with `going`, the host, the owner, the admins, and the members whose answer to this
     * event is Yes (#1515). "Replay only" and "No" do not pass. The answer counts also when the event is cancelled or
     * over.
     */
    final public function viewLocation(?User $user, Event $event): bool
    {
        if ($user === null || ! $user->belongsToCommunity($event->community) || ! $this->view($user, $event)) {
            return false;
        }

        return $event->location_visibility === KnowiiEventLocationVisibility::Members
            || CommunityRoles::isOwnerOrAdmin($user, $event->community)
            || self::isHost($user, $event)
            || $event->answerOf($user) === KnowiiEventAnswer::Yes;
    }

    /**
     * Read the replay link of the event (#1516): a member of the community for whom `view` passes, whatever
     * `location_visibility` says, so that a member who answered Replay only, No or nothing gets the replay of a `going`
     * event too. Never a visitor, never a person who is not a member, also in a public space.
     */
    final public function viewReplay(?User $user, Event $event): bool
    {
        return $user !== null && $user->belongsToCommunity($event->community) && $this->view($user, $event);
    }

    /**
     * Answer an event, change the answer or clear it (#1515): a member of the community who can see the event, while it
     * is published and not over. The owner, the admins and the moderators answer like members. Never a person who is
     * not a member, also in a public space.
     */
    final public function answer(User $user, Event $event): bool
    {
        return $user->belongsToCommunity($event->community)
            && $this->view($user, $event)
            && $event->state === KnowiiEventState::Published
            && ! $event->isPast();
    }

    /**
     * Comment the event, reply to a comment, and react to its comments (#1516): a member of the community who can see
     * the event, while it is not a draft. The owner, the admins and the moderators comment like members; a published, a
     * cancelled and a past event take comments. Never a person who is not a member, also in a public space. A draft takes
     * none, also from its host, the owner and the admins: a reply or a mention would tell a member who cannot see it.
     */
    final public function comment(User $user, Event $event): bool
    {
        return ! $event->isDraft()
            && $user->belongsToCommunity($event->community)
            && $this->view($user, $event);
    }

    /**
     * Read who answered Yes and Replay only, and how many answered No (#1515): the owner, the admins and the host.
     * Never a moderator, also with the right to delete content: the answers are personal data that moderation does not
     * need. The answers of a draft are read by nobody.
     */
    final public function viewAnswers(User $user, Event $event): bool
    {
        return ! $event->isDraft()
            && (CommunityRoles::isOwnerOrAdmin($user, $event->community) || self::isHost($user, $event));
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
