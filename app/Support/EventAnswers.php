<?php

namespace App\Support;

use App\Enums\KnowiiEventAnswer;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the reads need of the answers to events (#1515, ADR 0053, "The answers and the places"). It only reads: the
 * answers are written by `AnswerEvent`.
 */
final class EventAnswers
{
    /**
     * Give each event of a list the answer of the reader and its number of Yes answers, with two queries for the whole
     * list: the resource and the policy of an event then ask the database nothing more. A visitor has no answer.
     *
     * @param  Collection<int, Event>  $events
     */
    public static function preload(Collection $events, ?User $user): void
    {
        if ($user === null || $events->isEmpty()) {
            return;
        }

        $mine = EventAnswer::query()->where('user_id', $user->id)->whereIn('event_id', $events->modelKeys())->pluck('answer', 'event_id');
        // Only an event with a limit shows its places. Only the Yes answers of the members who can read the space of
        // their event today take a place (EventAnswer::scopeOfReaders): the database counts them, for the whole list
        $limited = $events->filter(static fn (Event $event): bool => $event->capacity !== null)->modelKeys();
        $yes = $limited === []
            ? collect()
            : EventAnswer::query()
                ->ofReaders()
                ->whereIn('event_id', $limited)
                ->where('answer', KnowiiEventAnswer::Yes->value)
                ->toBase()
                ->groupBy('event_id')
                ->selectRaw('event_id, count(*) as total')
                ->pluck('total', 'event_id');

        foreach ($events as $event) {
            $answer = $mine->get($event->id);
            $total = $yes->get($event->id);
            // The Yes answers are counted only for an event with a limit: the others keep their count unknown
            $event->knowAnswers($user, $answer instanceof KnowiiEventAnswer ? $answer : null, $event->capacity === null ? null : (is_numeric($total) ? (int) $total : 0));
        }
    }

    /**
     * Run the serialization of a list of events of one community for a reader: the answers of the reader and the places
     * are loaded first (preload), and the role of the reader in the community is read once for the whole list, so that
     * the policy of each event asks the database nothing.
     *
     * @template TReturn
     *
     * @param  Collection<int, Event>  $events
     * @param  Closure(): TReturn  $serialize
     * @return TReturn
     */
    public static function forList(Collection $events, ?User $user, Community $community, Closure $serialize)
    {
        if ($user === null || $events->isEmpty()) {
            return $serialize();
        }
        self::preload($events, $user);

        return CommunityRoles::withKnownAdmins($community, [$user->id], $serialize);
    }

    /**
     * The members who gave this answer, by name then by username, and the number of each answer: for the host, the owner
     * and the admins (EventPolicy::viewAnswers). Only the people who are members of the community and can read the space
     * today (EventAnswer::scopeOfReaders, the rule that the places follow too): a member who left, and a member who lost
     * the access to the space, are in no list and in no count. No name for "No", and no time of an answer.
     *
     * The database filters, counts, sorts and cuts the page: one query for the counts and one for the members of the
     * page, whatever the number of answers.
     *
     * @return array{items: Collection<int, User>, total: int, counts: array{yes: int, replay: int, no: int}}
     */
    public static function listOf(Event $event, KnowiiEventAnswer $answer, int $limit, int $offset): array
    {
        $answers = EventAnswer::query()->where('event_id', $event->id)->ofReaders();

        $totals = (clone $answers)->toBase()->groupBy('answer')->selectRaw('answer, count(*) as total')->pluck('total', 'answer');
        $count = static function (KnowiiEventAnswer $of) use ($totals): int {
            $total = $totals->get($of->value);

            return is_numeric($total) ? (int) $total : 0;
        };
        $counts = ['yes' => $count(KnowiiEventAnswer::Yes), 'replay' => $count(KnowiiEventAnswer::Replay), 'no' => $count(KnowiiEventAnswer::No)];

        $items = User::query()
            ->whereIn('id', (clone $answers)->where('answer', $answer->value)->select('user_id'))
            ->orderByRaw('lower(name)')
            ->orderByRaw('lower(username)')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return ['items' => $items, 'total' => $counts[$answer->value], 'counts' => $counts];
    }
}
