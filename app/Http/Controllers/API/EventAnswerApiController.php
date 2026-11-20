<?php

namespace App\Http\Controllers\API;

use App\Actions\Events\EventPublication;
use App\Contracts\Events\AnswersEvents;
use App\Enums\KnowiiEventAnswer;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Support\EventAnswers;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The answers of members to an event (#1515, ADR 0053): Yes, Replay only or No. A member reads and writes their own
 * answer, and nobody writes the answer of another member. The host, the owner and the admins read who answered Yes and
 * Replay only. A person who may not see the event gets the answer of an event that does not exist (404) from every route.
 * The writes are delegated to the action (ADR 0006).
 */
class EventAnswerApiController extends Controller
{
    use ApiResponses;

    private const string MESSAGE = 'Your answer';

    /**
     * Get your answer to an event: `answer` is yes, replay or no, and null when you did not answer. For the members of
     * the community who can see the event (403 for a person who is not a member). A cancelled event and an event that
     * ended still give the answer that you gave.
     *
     * @throws AuthenticationException
     */
    final public function show(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        EventPublication::assertVisible($user, $event);
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community answer an event');
        }

        $answer = $event->answers()->where('user_id', $user->id)->first() ?? new EventAnswer;

        return self::success(self::MESSAGE, null, $answer->toResource());
    }

    /**
     * Set your answer to an event: it replaces the answer that you gave before. For the members of the community who
     * can see the event, while it is published and not over: a cancelled event and an event that ended answer 422. A new
     * Yes on an event that is full answers 422, "This event is full."; the same answer again changes nothing. Only you
     * and the host, the owner and the admins of the community see your answer. Nobody is told.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('answer', description: 'yes, no, or replay ("Replay only": I cannot come, I want the replay). replay is accepted only when the event has replay_expected true (400 otherwise)', required: true, type: 'string', example: 'yes')]
    final public function update(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $answer = app(AnswersEvents::class)->answer($request->user() ?? throw new AuthenticationException, $event, $request->only(['answer']));

        return self::success(self::MESSAGE, null, $answer->toResource());
    }

    /**
     * Clear your answer to an event, while it is published and not over (422 otherwise). A Yes that goes frees a place;
     * nobody takes it for another member. It answers 204 also when you did not answer.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Event $event): Response
    {
        app(AnswersEvents::class)->clear($request->user() ?? throw new AuthenticationException, $event);

        return self::deleted();
    }

    /**
     * The members who answered an event, for its host, the owner and the admins of the community (403 for the others,
     * a moderator included): the members who answered Yes, or the members who answered Replay only, by name. `metadata`
     * holds the pagination and `counts`: the number of Yes, of Replay only and of No. The members who answered No are
     * never named, and no time of an answer is given. Only the people who are members today and can read the space are
     * listed and counted. The list of a draft is closed (403). Offset pagination (ADR 0011).
     *
     * @throws AuthenticationException
     */
    #[QueryParameter('answer', description: 'yes (default) or replay', type: 'string', example: 'yes')]
    #[QueryParameter('limit', description: 'Number of members (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of members to skip (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        EventPublication::assertVisible($user, $event);
        Gate::authorize('viewAnswers', $event);

        $answer = $request->query('answer', KnowiiEventAnswer::Yes->value);
        if ($answer !== KnowiiEventAnswer::Yes->value && $answer !== KnowiiEventAnswer::Replay->value) {
            throw ValidationException::withMessages(['answer' => 'Use yes or replay']);
        }

        $pagination = Pagination::fromRequest($request);
        $list = EventAnswers::listOf($event, KnowiiEventAnswer::from($answer), $pagination->limit, $pagination->offset);

        return self::success('Answers', [
            'pagination' => $pagination->metadata($list['total']),
            'counts' => $list['counts'],
        ], $list['items']->toResourceCollection(AuthorResource::class));
    }
}
