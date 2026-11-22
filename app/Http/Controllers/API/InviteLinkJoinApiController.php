<?php

namespace App\Http\Controllers\API;

use App\Contracts\InviteLinks\JoinsCommunitiesThroughInviteLinks;
use App\Exceptions\InviteLinkUnusableException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InviteLinks;
use App\Support\InviteLinkTexts;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The person who opens an invite link (#1391, ADR 0062): what the link opens, and the join. The token is the one of the
 * address of the link. A link that does not work (an unknown token, a link that is off, ended, full or deleted, or a link
 * of a personal community) gives one 404 with one message, so a person learns nothing about it. The answers never hold
 * the name of the link, its groups, its tags, its counts or its end.
 */
class InviteLinkJoinApiController extends Controller
{
    use ApiResponses;

    /**
     * Read what an invite link opens: the community (its public shape, or the shape of a member for a member) and the state
     * of the caller: `join` (the caller can join), `member` (the caller is a member already) or `blocked` (a manager
     * removed the caller: a link does not bring them back). Limited per minute.
     *
     * @throws AuthenticationException
     */
    final public function show(Request $request, string $inviteLinkToken): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $link = InviteLinks::usable($inviteLinkToken);
        if ($link === null) {
            return self::notFoundIssue(InviteLinkTexts::UNUSABLE);
        }

        return self::success('Invite link', null, [
            'community' => $link->community->toResource(),
            'state' => InviteLinks::stateFor($user, $link->community)->value,
        ]);
    }

    /**
     * Join the community of an invite link, as a member, with the access groups and the member tags of the link. The email
     * address of the caller must be verified. A member gets a 422 "You are already a member of this community" and a
     * person whom a manager removed a 422 with the text of the block: nothing changes for them. Returns the community as a
     * member sees it. Limited per minute.
     *
     * @throws AuthenticationException
     */
    final public function join(Request $request, string $inviteLinkToken): JsonResponse
    {
        try {
            $community = app(JoinsCommunitiesThroughInviteLinks::class)->join($request->user() ?? throw new AuthenticationException, $inviteLinkToken);
        } catch (InviteLinkUnusableException) {
            return self::notFoundIssue(InviteLinkTexts::UNUSABLE);
        }

        return self::created($community->loadCount('users')->toResource()->withLargeFields(), 'Community joined');
    }
}
