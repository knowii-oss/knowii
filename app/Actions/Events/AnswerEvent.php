<?php

namespace App\Actions\Events;

use App\Contracts\Events\AnswersEvents;
use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiEventState;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Visus\Cuid2\Cuid2;

/**
 * The answer of a member to an event (#1515, ADR 0053, "The answers and the places"). Only the member writes their own
 * answer: no manager adds a member or changes an answer, and nothing answers Yes for a member.
 *
 * The refusals, in this order: a person who may not see the event gets the answer of an event that does not exist (404);
 * a person who is not a member is refused (403); a draft, a cancelled event and an event that ended take no answer, no
 * change and no clear (422); a wrong field is a field error (400); a new Yes on a full event is refused (422).
 *
 * The limit of places holds when two members answer at the same moment: every answer and every clear of one event
 * waits for the lock of the row of the event, then reads the state, the end and the limit again and counts the Yes
 * answers. UpdateEvent and SetEventState take the same lock. The unique index on the event and the member stops a second
 * row when the same member sends two answers at once.
 *
 * Only the Yes answers of the members who can read the space today take a place (EventAnswer::scopeOfReaders). Inside
 * the lock the action also reads the member again: the membership and the access as they are now decide, and the row of
 * the membership stays as it is until the commit. A member who leaves at the same moment leaves no answer behind.
 *
 * The log lines hold the cuid of the event only: never the answer, the name or the cuid of the member.
 */
class AnswerEvent implements AnswersEvents
{
    public const string FULL_MESSAGE = 'This event is full.';

    public const string CANCELLED_MESSAGE = 'This event is cancelled. It takes no answers.';

    public const string OVER_MESSAGE = 'This event is over. It takes no answers.';

    public const string DRAFT_MESSAGE = 'This event is a draft. It takes no answers.';

    public const string NO_REPLAY_MESSAGE = 'This event has no replay.';

    final public function answer(User $user, Event $event, array $input): EventAnswer
    {
        Log::info('Processing request to answer an event', ['event' => $event->cuid]);

        self::assertAllowed($user, $event);

        /** @var array{answer: string} $validated */
        $validated = Validator::make($input, [
            'answer' => ['required', 'string', Rule::enum(KnowiiEventAnswer::class)],
        ])->validate();
        $target = KnowiiEventAnswer::from($validated['answer']);
        self::assertOffered($event, $target);

        [$answer, $placesChanged] = DB::transaction(function () use ($user, $event, $target): array {
            $locked = self::lock($user, $event);
            self::assertOffered($locked, $target);

            $current = EventAnswer::query()->where('event_id', $locked->id)->where('user_id', $user->id)->first();
            // The same answer again changes nothing, and is never refused for the limit
            if ($current !== null && $current->answer === $target) {
                return [$current, false];
            }

            $wasYes = $current?->answer === KnowiiEventAnswer::Yes;
            // The database counts the Yes answers that take a place: the ones of the members who can read the space today
            if ($target === KnowiiEventAnswer::Yes && $locked->capacity !== null && $locked->yesCount() >= $locked->capacity) {
                throw new BusinessException(self::FULL_MESSAGE);
            }

            // One statement: the unique index decides when the same member sends two answers at once
            EventAnswer::query()->upsert(
                [['cuid' => (new Cuid2)->toString(), 'event_id' => $locked->id, 'user_id' => $user->id, 'community_id' => $locked->community_id, 'answer' => $target->value]],
                ['event_id', 'user_id'],
                ['answer', 'updated_at'],
            );

            return [
                EventAnswer::query()->where('event_id', $locked->id)->where('user_id', $user->id)->firstOrFail(),
                $locked->capacity !== null && $wasYes !== ($target === KnowiiEventAnswer::Yes),
            ];
        });

        $event->forgetAnswers();
        Log::info('Event answered', ['event' => $event->cuid]);
        self::placesChanged($event, $placesChanged);

        return $answer;
    }

    final public function clear(User $user, Event $event): void
    {
        Log::info('Processing request to clear the answer to an event', ['event' => $event->cuid]);

        self::assertAllowed($user, $event);

        $placesChanged = DB::transaction(function () use ($user, $event): bool {
            $locked = self::lock($user, $event);

            $current = EventAnswer::query()->where('event_id', $locked->id)->where('user_id', $user->id)->first();
            if ($current === null) {
                return false;
            }
            $current->delete();

            // A member who leaves a Yes frees a place. Nobody is promoted: there is no waiting list
            return $locked->capacity !== null && $current->answer === KnowiiEventAnswer::Yes;
        });

        $event->forgetAnswers();
        Log::info('Event answer cleared', ['event' => $event->cuid]);
        self::placesChanged($event, $placesChanged);
    }

    /**
     * The refusals that need no field: 404, then 403 for a person who is not a member, then the state of the event (422),
     * then the policy, which holds the whole rule.
     */
    private static function assertAllowed(User $user, Event $event): void
    {
        EventPublication::assertVisible($user, $event);
        if (! $user->belongsToCommunity($event->community)) {
            throw new AuthorizationException('Only the members of the community answer an event');
        }
        self::assertOpen($event);
        Gate::forUser($user)->authorize('answer', $event);
    }

    /**
     * A draft takes no answers. A cancelled event and an event that ended keep their answers as they are: no new answer,
     * no change and no clear, until the host publishes a cancelled event again.
     */
    private static function assertOpen(Event $event): void
    {
        match (true) {
            $event->state === KnowiiEventState::Draft => throw new BusinessException(self::DRAFT_MESSAGE),
            $event->state === KnowiiEventState::Cancelled => throw new BusinessException(self::CANCELLED_MESSAGE),
            $event->isPast() => throw new BusinessException(self::OVER_MESSAGE),
            default => null,
        };
    }

    /**
     * "Replay only" exists only when the host says that a replay will exist: else a field error
     */
    private static function assertOffered(Event $event, KnowiiEventAnswer $answer): void
    {
        if ($answer === KnowiiEventAnswer::Replay && ! $event->replay_expected) {
            throw ValidationException::withMessages(['answer' => self::NO_REPLAY_MESSAGE]);
        }
    }

    /**
     * The row of the event, locked until the end of the transaction, with its state, its end and its limit as they are
     * now. An answer waits here for a cancel, for a change of the limit and for the other answers of the same event.
     *
     * Then the member as they are now, and every refusal again in its order: the checks before the lock used what the
     * request loaded, and a leave or a lost access group may have passed since. The row of the membership is locked for
     * the length of the transaction: a leave that runs at the same moment waits for the commit and then deletes the
     * answer with the others (Community::removeUser), or it ends first and the answer is refused here.
     */
    private static function lock(User $user, Event $event): Event
    {
        $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first()
            ?? throw (new ModelNotFoundException)->setModel(Event::class, [$event->cuid]);

        DB::table('community_members')->where('community_id', $locked->community_id)->where('user_id', $user->id)->sharedLock()->get(['id']);
        $member = User::query()->whereKey($user->id)->first() ?? throw new AuthorizationException('Only the members of the community answer an event');
        self::assertAllowed($member, $locked);

        return $locked;
    }

    /**
     * The live update (#1515): only when the number of Yes answers of an event with a limit changed, so that the open
     * pages read "places left" again. Every other change of an answer sends nothing: a person who listens to the channel
     * must not learn the moment of each answer. The payload names the event and nobody.
     */
    private static function placesChanged(Event $event, bool $changed): void
    {
        if ($changed) {
            event(SpaceContentChanged::forEventAnswers($event));
        }
    }
}
