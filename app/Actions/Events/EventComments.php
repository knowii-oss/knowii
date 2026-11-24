<?php

namespace App\Actions\Events;

use App\Exceptions\BusinessException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The refusals of the comments of an event (#1516, ADR 0053, "Comments and after the event"), before the rules of the
 * comments themselves (CommentPolicy, EventPolicy::comment): the same order as the other routes of an event.
 *
 * 1. A person who may not see the event gets the answer of an event that does not exist (404), also for a write of
 *    their own comment. On the API, a space that the person cannot read answers before (`SpaceAccess`): a member who
 *    lost the access to the space learns nothing more of the event.
 * 2. A person who is not a member of the community is refused (403).
 * 3. A new comment on a draft is refused (422): a draft takes no comments.
 *
 * The comment actions call it for a comment of an event, so that every caller gets the same answers.
 */
final class EventComments
{
    public const string NOT_A_MEMBER_MESSAGE = 'Only the members of the community comment an event';

    public const string DRAFT_MESSAGE = 'This event is a draft. It takes no comments.';

    /**
     * @throws ModelNotFoundException<Event>
     * @throws AuthorizationException
     * @throws BusinessException
     */
    public static function assertAllowed(User $user, Event $event, bool $create): void
    {
        EventPublication::assertVisible($user, $event);
        if (! $user->belongsToCommunity($event->community)) {
            throw new AuthorizationException(self::NOT_A_MEMBER_MESSAGE);
        }
        if ($create) {
            self::assertNotDraft($event);
        }
    }

    /**
     * @throws BusinessException
     */
    public static function assertNotDraft(Event $event): void
    {
        if ($event->isDraft()) {
            throw new BusinessException(self::DRAFT_MESSAGE);
        }
    }
}
