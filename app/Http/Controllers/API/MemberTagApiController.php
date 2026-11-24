<?php

namespace App\Http\Controllers\API;

use App\Contracts\MemberTags\AddsMemberTagMembers;
use App\Contracts\MemberTags\CreatesMemberTags;
use App\Contracts\MemberTags\DeletesMemberTags;
use App\Contracts\MemberTags\RemovesMemberTagMembers;
use App\Contracts\MemberTags\UpdatesMemberTags;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\MemberTagsOfMembers;
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
 * Member tags (#1389, ADR 0060): labels that the managers of a community put on its members, for example "Founder". The
 * owner and the admins define the tags; they and the moderators with the "manage members" right put them on members. A
 * tag is for the members (they see it on the members who have it) or for the managers only. A tag of the managers does
 * not exist for another reader: the binding of `{memberTag}` (MemberTagsOfMembers::bindRoute) answers 404, with the body
 * of a tag that does not exist, before the token abilities and any other check. Changes are delegated to the actions
 * (ADR 0006).
 */
class MemberTagApiController extends Controller
{
    use ApiResponses;

    /**
     * List the member tags of the community, by name. The people who manage the members get every tag with its member
     * count; another member gets the tags of the members, with `members_count` null. No pagination: a community has at
     * most 100 tags.
     */
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('view', $community);
        $reader = $request->user();

        return self::success('Member tags', null, MemberTagsOfMembers::ofCommunity($community, $reader instanceof User ? $reader : null)->toResourceCollection());
    }

    /**
     * Create a member tag (owner and admins). Without a color it is gray; without a visibility it is for the managers only.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the tag (2 to 32 characters, one line), unique in the community without the case', required: true, type: 'string', example: 'Founder')]
    #[BodyParameter('color', description: 'One color of the palette. Default: gray', required: false, type: "'gray'|'red'|'orange'|'yellow'|'green'|'teal'|'blue'|'purple'", example: 'purple')]
    #[BodyParameter('visibility', description: 'Who sees the tag: the members of the community, or the managers only. Default: managers', required: false, type: "'members'|'managers'", example: 'members')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $tag = app(CreatesMemberTags::class)->create($request->user() ?? throw new AuthenticationException, $community, $request->only(['name', 'color', 'visibility']));

        return self::created($tag->loadCount('members')->toResource(), 'Member tag created');
    }

    /**
     * Rename a member tag, or change its color or its visibility (owner and admins). Only the fields of the request change.
     * A rename keeps the members of the tag. A change to `members` shows the tag on every member who has it.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the tag (2 to 32 characters, one line), unique in the community without the case', required: false, type: 'string', example: 'Founder')]
    #[BodyParameter('color', description: 'One color of the palette', required: false, type: "'gray'|'red'|'orange'|'yellow'|'green'|'teal'|'blue'|'purple'", example: 'teal')]
    #[BodyParameter('visibility', description: 'Who sees the tag: the members of the community, or the managers only', required: false, type: "'members'|'managers'", example: 'managers')]
    final public function update(Request $request, Community $community, MemberTag $memberTag): JsonResponse
    {
        $tag = app(UpdatesMemberTags::class)->update($request->user() ?? throw new AuthenticationException, $memberTag, $request->only(['name', 'color', 'visibility']));

        return self::success('Member tag updated', null, $tag->loadCount('members')->toResource());
    }

    /**
     * Delete a member tag (owner and admins). Every member who has it loses it. It cannot be undone.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, MemberTag $memberTag): Response
    {
        app(DeletesMemberTags::class)->delete($request->user() ?? throw new AuthenticationException, $memberTag);

        return self::deleted();
    }

    /**
     * List the members who have the tag, by name (owner, admins, and moderators with the "manage members" right, also for
     * a tag of the members).
     */
    #[QueryParameter('limit', description: 'Number of members (default 20, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Number of members to skip', type: 'int', example: 0)]
    final public function members(Request $request, Community $community, MemberTag $memberTag): JsonResponse
    {
        Gate::authorize('viewMemberTags', $community);

        $page = Pagination::fromRequest($request)->apply(
            User::query()
                ->join('member_tag_members as links', 'links.user_id', '=', 'users.id')
                ->where('links.member_tag_id', $memberTag->id)
                ->select('users.*')
                ->orderBy('users.name')
                ->orderBy('users.id')
        );

        return self::success('Members of the member tag', $page['metadata'], $page['items']->toResourceCollection(AuthorResource::class));
    }

    /**
     * Put the tag on a member of the community (the owner counts), named by exactly one of member (user cuid) or username.
     * Putting it twice changes nothing. A member has at most 20 tags in a community. Nothing tells the member.
     * At most 10 adds and removals a minute for each account.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('member', description: 'Cuid of the user', required: false, type: 'string')]
    #[BodyParameter('username', description: 'Username of the member', required: false, type: 'string', example: 'ada-lovelace')]
    final public function addMember(Request $request, Community $community, MemberTag $memberTag): JsonResponse
    {
        $member = app(AddsMemberTagMembers::class)->add($request->user() ?? throw new AuthenticationException, $memberTag, $request->only(['member', 'username', 'email']));

        return self::success('Member tag added to the member', null, $member->toResource(AuthorResource::class));
    }

    /**
     * Take the tag off a member. A member who does not have it gives a 404.
     *
     * @throws AuthenticationException
     */
    final public function removeMember(Request $request, Community $community, MemberTag $memberTag, string $member): Response
    {
        app(RemovesMemberTagMembers::class)->remove($request->user() ?? throw new AuthenticationException, $memberTag, $member);

        return self::deleted();
    }
}
