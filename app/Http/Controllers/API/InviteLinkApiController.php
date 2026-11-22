<?php

namespace App\Http\Controllers\API;

use App\Contracts\InviteLinks\CreatesInviteLinks;
use App\Contracts\InviteLinks\DeletesInviteLinks;
use App\Contracts\InviteLinks\UpdatesInviteLinks;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Support\InviteLinks;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Invite links (#1391, ADR 0062): addresses that the owner and the admins of a community share. Any person who opens one
 * can join the community as a member, with the access groups and the member tags of the link. These routes are for the
 * owner and the admins only. A link of another community gives a 404 (scoped binding), then a 403, then a 400 for a
 * wrong field, then the limit of 50 links (422). Changes are delegated to the actions (ADR 0006).
 */
class InviteLinkApiController extends Controller
{
    use ApiResponses;

    /**
     * List the invite links of the community, newest first, with their state, their address, what they give and how many
     * people joined through them. No pagination: a community has at most 50 links.
     */
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('manageInviteLinks', $community);

        $links = $community->inviteLinks()->orderByDesc('created_at')->orderByDesc('id')->get();
        InviteLinks::loadForManagers($links);

        return self::success('Invite links', null, $links->toResourceCollection());
    }

    /**
     * Create an invite link. It gives the role member. Without `active` it is on; without `expires_at` and `max_uses` it
     * has no end and no maximum. Anyone who has the address can join while it works.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the link, for the managers only (2 to 64 characters, one line)', required: true, type: 'string', example: 'Course buyers')]
    #[BodyParameter('active', description: 'Whether the link works. Default: true', required: false, type: 'bool', example: true)]
    #[BodyParameter('expires_at', description: 'The end of the link, an ISO 8601 time with its zone, in the future. Null: no end', required: false, type: 'string|null', example: '2026-12-31T23:00:00Z')]
    #[BodyParameter('max_uses', description: 'The most people who can join through the link (1 to 10000). Null: no maximum', required: false, type: 'int|null', example: 100)]
    #[BodyParameter('access_groups', description: 'Cuids of access groups of the community that a person gets at the join (50 at most). A group that a rule on an entitlement feeds is refused', required: false, type: 'array<string>', example: ['tz4a98xxat96iws9zmbrgj3a'])]
    #[BodyParameter('member_tags', description: 'Cuids of member tags of the community that a person gets at the join (20 at most)', required: false, type: 'array<string>', example: ['pfh0haxfpzowht3oi213cqos'])]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $link = app(CreatesInviteLinks::class)->create($request->user() ?? throw new AuthenticationException, $community, $request->only(['name', 'active', 'expires_at', 'max_uses', 'access_groups', 'member_tags']));

        return self::created($link->toResource(), 'Invite link created');
    }

    /**
     * Read one invite link.
     */
    final public function show(Request $request, Community $community, InviteLink $inviteLink): JsonResponse
    {
        Gate::authorize('manageInviteLinks', $community);

        InviteLinks::loadForManagers([$inviteLink]);

        return self::success('Invite link', null, $inviteLink->toResource());
    }

    /**
     * Change an invite link: only the fields of the request change. `access_groups` and `member_tags` replace the lists.
     * `active` false stops the link at once. A maximum at or below the number of joins makes the link full. The people who
     * joined before keep what they got.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the link, for the managers only (2 to 64 characters, one line)', required: false, type: 'string', example: 'Course buyers')]
    #[BodyParameter('active', description: 'Whether the link works', required: false, type: 'bool', example: false)]
    #[BodyParameter('expires_at', description: 'The end of the link, an ISO 8601 time with its zone, in the future. Null clears it', required: false, type: 'string|null', example: '2026-12-31T23:00:00Z')]
    #[BodyParameter('max_uses', description: 'The most people who can join through the link (1 to 10000). Null clears it', required: false, type: 'int|null', example: 100)]
    #[BodyParameter('access_groups', description: 'Cuids of access groups of the community: replaces the list', required: false, type: 'array<string>', example: ['tz4a98xxat96iws9zmbrgj3a'])]
    #[BodyParameter('member_tags', description: 'Cuids of member tags of the community: replaces the list', required: false, type: 'array<string>', example: ['pfh0haxfpzowht3oi213cqos'])]
    final public function update(Request $request, Community $community, InviteLink $inviteLink): JsonResponse
    {
        $link = app(UpdatesInviteLinks::class)->update($request->user() ?? throw new AuthenticationException, $inviteLink, $request->only(['name', 'active', 'expires_at', 'max_uses', 'access_groups', 'member_tags']));

        return self::success('Invite link updated', null, $link->toResource());
    }

    /**
     * Delete an invite link. It stops working at once, and the list of who joined through it goes. The members who joined
     * through it stay, with their groups and tags. It cannot be undone.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, InviteLink $inviteLink): Response
    {
        app(DeletesInviteLinks::class)->delete($request->user() ?? throw new AuthenticationException, $inviteLink);

        return self::deleted();
    }

    /**
     * List who joined through the link, newest first: the member and the date of the join. A member who left the community
     * is not in the list, and stays in the count of the link.
     */
    #[QueryParameter('limit', description: 'Number of entries (default 20, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Number of entries to skip', type: 'int', example: 0)]
    final public function uses(Request $request, Community $community, InviteLink $inviteLink): JsonResponse
    {
        Gate::authorize('manageInviteLinks', $community);

        $page = Pagination::fromRequest($request)->apply(
            InviteLinkUse::query()
                ->where('invite_link_id', $inviteLink->id)
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
        );

        return self::success('Who joined through the invite link', $page['metadata'], $page['items']->toResourceCollection());
    }
}
