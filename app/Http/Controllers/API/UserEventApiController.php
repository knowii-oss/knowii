<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\MyEvents;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "My events" (#1515, design 6.8, ADR 0053): the events of the current member in all their communities. It only reads.
 */
class UserEventApiController extends Controller
{
    use ApiResponses;

    /**
     * Your events in all your communities: the events that you answered Yes or Replay only (published or cancelled, never a
     * draft), and the events that you host (also your drafts). Only the events of the spaces that you can read today as a
     * member: never an event of a community that you left. `upcoming` (the default): the events that end now or later,
     * your drafts first, then the next start first. `past`: the events that ended, the newest start first. Each event
     * carries its space and its community, and the fields of `GET .../events/{event}` without the description: the meeting
     * address and the place only when you may see them, your own answer, never the answer of another member. Offset
     * pagination (ADR 0011).
     *
     * @throws AuthenticationException
     */
    #[QueryParameter('scope', description: 'upcoming (default) or past', type: 'string', example: 'upcoming')]
    #[QueryParameter('answer', description: 'yes (the events that you answered Yes), replay (Replay only) or host (the events that you host). Default: the events that you answered Yes or Replay only, and the events that you host', type: 'string', example: 'yes')]
    #[QueryParameter('limit', description: 'Number of events (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of events to skip (default 0)', type: 'int', example: 0)]
    #[OpenApiResponse(400, 'A wrong scope ("Use upcoming or past") or a wrong answer ("Use yes, replay or host")', type: 'array{message: string, errors: array<string, list<string>>}')]
    #[OpenApiResponse(403, 'A token without the ability events:read', type: 'array{message: string}')]
    final public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        $scope = $request->query('scope', MyEvents::SCOPE_UPCOMING);
        if ($scope !== MyEvents::SCOPE_UPCOMING && $scope !== MyEvents::SCOPE_PAST) {
            throw ValidationException::withMessages(['scope' => 'Use upcoming or past']);
        }
        $answer = $request->query('answer');
        if ($answer !== null && ! in_array($answer, MyEvents::ANSWERS, true)) {
            throw ValidationException::withMessages(['answer' => 'Use yes, replay or host']);
        }

        $page = Pagination::fromRequest($request)->apply(MyEvents::query($user, $scope, is_string($answer) ? $answer : null));

        return MyEvents::forList($page['items'], $user, static fn (): JsonResponse => self::success('My events', $page['metadata'], $page['items']->toResourceCollection()));
    }
}
