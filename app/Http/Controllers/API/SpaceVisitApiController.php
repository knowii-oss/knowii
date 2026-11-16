<?php

namespace App\Http\Controllers\API;

use App\Contracts\Notifications\TracksSpaceVisits;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Space;
use App\Traits\ApiResponses;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The visit of a space (#1476, ADR 0048): the read marker behind the dots of the navigation
 */
class SpaceVisitApiController extends Controller
{
    use ApiResponses;

    /**
     * The current member visited the space (the client calls it when a space is open without a page load): the dot of the
     * space goes. One row per member and space, replaced at each visit. Only a member of the community who can view the
     * space visits it (403 otherwise): a visitor and a signed-in person who is not a member never get a row.
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    final public function update(Request $request, Community $community, Space $space): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        if (! app(TracksSpaceVisits::class)->visit($user, $space)) {
            throw new AuthorizationException('Only the members who can view the space visit it');
        }

        return self::success('Space visited');
    }
}
