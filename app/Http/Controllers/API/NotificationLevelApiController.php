<?php

namespace App\Http\Controllers\API;

use App\Contracts\Notifications\ChoosesSpaceLevels;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Space;
use App\Models\User;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The level that the current member chose for a space (#1475, ADR 0042, design 2.2): `all` (Everything), `mine` (Only
 * what is for me) or `none` (Muted). `level` is the level that applies, `chosen` says whether the member chose it, and
 * `default` is the level that applies without a choice (what a manager set, else the built-in default). Only a member of
 * the community who can view the space has a level for it: a space that the person cannot read answers as a space that
 * does not exist (404) for a person who is not a member, and 403 for a member outside its access groups; a person who is
 * not a member gets 403 in a public space and on the list of the community.
 */
class NotificationLevelApiController extends Controller
{
    use ApiResponses;

    /**
     * The levels of the current member for every space of the community that they can view, in the order of the
     * navigation, in one call (for a settings screen). A link space has no level and is not listed.
     *
     * @throws AuthenticationException
     */
    final public function index(Request $request, Community $community): JsonResponse
    {
        return self::successList('Notification levels', app(ChoosesSpaceLevels::class)->forCommunity(self::user($request), $community));
    }

    /**
     * The level of the current member for a space.
     *
     * @throws AuthenticationException
     */
    final public function show(Request $request, Community $community, Space $space): JsonResponse
    {
        return self::success('Notification level', null, app(ChoosesSpaceLevels::class)->show(self::user($request), $space));
    }

    /**
     * Choose a level for a space. `none` (Muted) gives the member nothing from the space, also when someone mentions
     * them, and deletes their open notifications of the space. `all` (Everything) exists for discussion spaces and chat
     * spaces only.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('level', description: 'all (Everything), mine (Only what is for me) or none (Muted)', required: true, type: 'string', example: 'mine')]
    final public function update(Request $request, Community $community, Space $space): JsonResponse
    {
        return self::success('Notification level saved', null, app(ChoosesSpaceLevels::class)->set(self::user($request), $space, $request->only(['level'])));
    }

    /**
     * Forget the choice of the current member: the default of the space applies again.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space): JsonResponse
    {
        return self::success('Notification level reset', null, app(ChoosesSpaceLevels::class)->reset(self::user($request), $space));
    }

    /**
     * @throws AuthenticationException
     */
    private static function user(Request $request): User
    {
        return $request->user() ?? throw new AuthenticationException;
    }
}
