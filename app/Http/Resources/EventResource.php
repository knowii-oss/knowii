<?php

namespace App\Http\Resources;

use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Models\Upload;
use App\Models\User;
use App\Support\EventAnswers;
use App\Support\MentionLinks;
use App\Support\VideoEmbed;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * An event of an events space (#1513, ADR 0053). The start and the end are UTC instants (ISO 8601), with the zone in
 * which the host entered them. The meeting address and the place are cut on the server (EventPolicy::viewLocation):
 * a visitor, a person who is not a member, and a member who may not read them get null and `can_view_location` false.
 * The Markdown and its HTML are large fields. The host has the cut of the authors of posts (ADR 0044, point 6).
 *
 * The answers (#1515): `my_answer` is the answer of the reader and of nobody else, `can_answer` is EventPolicy::answer,
 * `places_left` is for a member who can see an event that has a limit, and `capacity` (the limit, which with the places
 * that are left gives the number of Yes answers) is only for the people who read the answers or change the event. With
 * an API token, `capacity` also needs the ability that reads the answers (`event-answers:read`), as the list of the
 * answers does: a token without it reads what a member reads. A visitor and a person who is not a member get null,
 * false, null and null. No field names another member or counts answers.
 *
 * After the event (#1516): `replay_url` and `replay` (the click-to-load player of a lesson for a link of its two video
 * hosts, VideoEmbed::parse, else null) are for the readers who pass EventPolicy::viewReplay (`can_view_replay`): a
 * visitor and a person who is not a member get false, null and null, and nothing that says whether a replay exists.
 * `notes` and `notes_html` are large fields, for every reader of the event.
 *
 * WARNING: aligned with eventSchema (event.schema.ts)
 *
 * @mixin Event
 */
class EventResource extends AbstractKnowiiJsonResource
{
    private const string ANSWERS_ABILITY = 'event-answers:read';

    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var Event $event */
        $event = $this->resource;
        $user = $request->user();
        // A member who can see the event: the only reader who has an answer and who reads the places
        $member = $user instanceof User && $user->belongsToCommunity($event->community) && Gate::forUser($user)->check('view', $event) ? $user : null;
        // One event: the answer of the reader and the number of Yes answers, as a list loads them for its page
        if ($member !== null && ! $event->knowsAnswerOf($member)) {
            EventAnswers::preload(new Collection([$event]), $member);
        }
        $canViewLocation = Gate::forUser($user)->check('viewLocation', $event);
        $canViewReplay = Gate::forUser($user)->check('viewReplay', $event);
        $seesCapacity = $member !== null && self::tokenReadsAnswers($member)
            && (Gate::forUser($member)->check('viewAnswers', $event) || Gate::forUser($member)->check('update', $event));

        return [
            'cuid' => $event->cuid,
            'title' => $event->title,
            'slug' => $event->slug,
            'state' => $event->state,
            'starts_at' => $event->starts_at->toImmutable()->utc(),
            'ends_at' => $event->ends_at->toImmutable()->utc(),
            'time_zone' => $event->time_zone,
            // Derived from the end, never stored
            'is_past' => $event->isPast(),
            'location_visibility' => $event->location_visibility,
            'can_view_location' => $canViewLocation,
            'meeting_url' => $canViewLocation ? $event->meeting_url : null,
            'place' => $canViewLocation ? $event->place : null,
            // yes, replay or no: the answer of the reader. Null: no answer, a visitor, a person who is not a member
            'my_answer' => $member === null ? null : $event->answerOf($member),
            'can_answer' => $member !== null && Gate::forUser($member)->check('answer', $event),
            // The host says that a replay will exist: "Replay only" is an answer
            'replay_expected' => $event->replay_expected,
            // From 0, for a member who can see an event with a limit. Null: no limit, or not a member
            'places_left' => $member === null ? null : $event->placesLeft(),
            // The limit of places, for the people who read the answers or change the event. Null for the others
            'capacity' => $seesCapacity ? $event->capacity : null,
            'cover' => $this->whenLoaded('cover', fn (?Upload $cover) => $cover?->toResource(), null),
            // Empty: a decorative image
            'cover_alt' => $event->cover_alt,
            // Null when the account of the host was deleted ("Deleted member")
            'host' => $this->whenLoaded('host', fn (?User $host) => $host?->toResource(AuthorResource::class), null),
            'space' => $this->whenLoaded('space', fn (Space $space) => ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name]),
            // The community of the event, when a read loaded it (#1515): "My events" names it. A reader of the event knows it
            'community' => $this->whenLoaded('community', fn (Community $community) => ['cuid' => $community->cuid, 'slug' => $community->slug, 'name' => $community->name]),
            'body' => $this->serializeLargeFields ? $event->body : null,
            // A mention is a link for a member of the community only (MentionLinks)
            'body_html' => $this->serializeLargeFields ? MentionLinks::forReader($event->body_html, $request, $event->community_id) : null,
            // After the event (#1516): the replay for the members who can see the event, cut on the server
            'can_view_replay' => $canViewReplay,
            'replay_url' => $canViewReplay ? $event->replay_url : null,
            'replay' => $canViewReplay ? VideoEmbed::parse($event->replay_url) : null,
            // For every reader of the event, with the description. Empty: no notes
            'notes' => $this->serializeLargeFields ? $event->notes : null,
            'notes_html' => $this->serializeLargeFields ? MentionLinks::forReader($event->notes_html, $request, $event->community_id) : null,
            'created_at' => $event->created_at,
            'updated_at' => $event->updated_at,
        ];
    }

    /**
     * `capacity` with the places that are left gives the number of Yes answers: with an API token it needs the ability
     * of the list of the answers. A session has every ability (a TransientToken, or no token in a page request).
     */
    private static function tokenReadsAnswers(User $user): bool
    {
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can(self::ANSWERS_ABILITY);
    }
}
