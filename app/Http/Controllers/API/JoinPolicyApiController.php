<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\UpdatesCommunityJoinPolicy;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who can join a community (#1486, ADR 0044). The change is delegated to the action (ADR 0006).
 */
class JoinPolicyApiController extends Controller
{
    use ApiResponses;

    /**
     * Change who can join the community (owner and admins): `invitation` (the default) or `open` (a signed-in person with
     * a verified email address joins by themselves, as a member). Never for a personal community. Returns the community
     * with its join policy. Read the current value with GET community (`join_policy`).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('join_policy', description: 'invitation: people join with an invitation. open: anyone with an account can join and read the members-only spaces', required: true, type: 'string', example: 'open')]
    final public function update(Request $request, Community $community): JsonResponse
    {
        $community = app(UpdatesCommunityJoinPolicy::class)->update($request->user() ?? throw new AuthenticationException, $community, $request->only(['join_policy']));

        return self::success('Join policy updated', null, $community->loadCount('users')->toResource()->withLargeFields());
    }
}
