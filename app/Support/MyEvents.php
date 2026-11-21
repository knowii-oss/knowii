<?php

namespace App\Support;

use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiEventState;
use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * "My events" (#1515, design 6.8, ADR 0053 "The calendar file and My events"): the events of one member in all their
 * communities, for `GET user/events` and the page `/events`.
 *
 * Two ways in, one list: the events that the member answered Yes or Replay only (published or cancelled, never a draft),
 * and the events that the member hosts (every state: the own drafts too). For both, the member is a member of the
 * community of the event and can read its space today (Event::scopeReadableBy, the reading rule of the answers that
 * count): never an event of a community that the member left, of a "groups" space that they cannot read any more, or of
 * a public space of a community of which they are not a member. A draft of another host is never in it, also for the
 * owner and the admins. An event that the member hosts and answered is one item.
 *
 * The database filters, sorts and cuts the page. "Upcoming" and "Past" are the scopes of the list of a space.
 */
final class MyEvents
{
    public const string SCOPE_UPCOMING = 'upcoming';

    public const string SCOPE_PAST = 'past';

    // The values of `answer`: the events with that answer of the member, or the events that they host
    public const string ANSWER_YES = 'yes';

    public const string ANSWER_REPLAY = 'replay';

    public const string ANSWER_HOST = 'host';

    public const array ANSWERS = [self::ANSWER_YES, self::ANSWER_REPLAY, self::ANSWER_HOST];

    /**
     * What the resource of an event of the list needs: the space and the community name the row, and the policy reads
     * the access groups of the space and of its space group (SpacePolicy::view of a "groups" space)
     */
    public const array RELATIONS = ['host', 'cover', 'space.accessGroups', 'space.spaceGroup.accessGroups', 'community'];

    /**
     * The events of the member, in the order of the scope
     *
     * @param  ?string  $answer  yes, replay or host; null: both ways
     * @return Builder<Event>
     */
    public static function query(User $user, string $scope, ?string $answer = null): Builder
    {
        $query = self::events($user, $answer)->with(self::RELATIONS);

        return $scope === self::SCOPE_PAST ? $query->past() : $query->upcoming();
    }

    /**
     * The events of the member in both lists ("Upcoming" and "Past"), without an order and without the relations
     *
     * @param  ?string  $answer  yes, replay or host; null: both ways
     * @return Builder<Event>
     */
    public static function events(User $user, ?string $answer = null): Builder
    {
        $answers = match ($answer) {
            self::ANSWER_YES => [KnowiiEventAnswer::Yes->value],
            self::ANSWER_REPLAY => [KnowiiEventAnswer::Replay->value],
            default => [KnowiiEventAnswer::Yes->value, KnowiiEventAnswer::Replay->value],
        };

        // Through an answer: Yes or Replay only, on an event that members may see
        $throughAnswer = static function (Builder $query) use ($user, $answers): void {
            $query->whereIn('events.state', [KnowiiEventState::Published->value, KnowiiEventState::Cancelled->value])
                ->whereExists(static function (QueryBuilder $mine) use ($user, $answers): void {
                    $mine->selectRaw('1')
                        ->from('event_answers')
                        ->whereColumn('event_answers.event_id', 'events.id')
                        ->where('event_answers.user_id', $user->id)
                        ->whereIn('event_answers.answer', $answers);
                });
        };
        // As the host: every state, the own drafts too
        $asHost = static function (Builder $query) use ($user): void {
            $query->where('events.host_id', $user->id);
        };

        return Event::query()
            ->readableBy($user)
            ->where(static function (Builder $ways) use ($answer, $throughAnswer, $asHost): void {
                match ($answer) {
                    self::ANSWER_HOST => $asHost($ways),
                    self::ANSWER_YES, self::ANSWER_REPLAY => $throughAnswer($ways),
                    default => $ways->where($throughAnswer)->orWhere($asHost),
                };
            });
    }

    /**
     * Run the serialization of a page of the list for the member: each space knows its community (the policy reads it),
     * the answers of the member and the places load once for the page (EventAnswers::preload), and the roles of the member
     * in all the communities of the page are read with one query, so that the policy of each event asks the database
     * nothing more, whatever the number of communities.
     *
     * @template TReturn
     *
     * @param  Collection<int, Event>  $events
     * @param  Closure(): TReturn  $serialize
     * @return TReturn
     */
    public static function forList(Collection $events, User $user, Closure $serialize)
    {
        foreach ($events as $event) {
            $event->space->setRelation('community', $event->community);
        }
        EventAnswers::preload($events, $user);

        $communities = $events->map(static fn (Event $event): Community => $event->community)->unique('id')->values()->all();

        return CommunityRoles::withKnownAdminsIn($communities, [$user->id], $serialize);
    }
}
