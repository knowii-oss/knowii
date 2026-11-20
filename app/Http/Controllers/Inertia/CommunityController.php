<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiNoAccessKind;
use App\Enums\KnowiiSpaceType;
use App\Http\Resources\MemberWithRoleResource;
use App\Http\Resources\NewestPostResource;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityMemberQuery;
use App\Support\CommunityRoles;
use App\Support\EventAnswers;
use App\Support\NoAccess;
use App\Support\OidcEntitlements;
use App\Support\PublicCommunity;
use App\Support\SpaceTree;
use App\Support\UpcomingEvents;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\RedirectsActions;

class CommunityController extends Controller
{
    use RedirectsActions;

    // The details page lists the owner, the admins and the moderators: more than this is not a team, it is a directory
    private const int MAX_TEAM_MEMBERS = 50;

    /**
     * Show the community screen.
     */
    public function show(Request $request, string $slug): Response
    {
        $community = Community::where('slug', $slug)->firstOrFail();

        // Someone who is not a member (a visitor, a signed-in outsider) gets the public page, or nothing (#1485)
        if (! PublicCommunity::isMember($request->user(), $community)) {
            return $this->showPublic($request, $community);
        }

        Gate::authorize('view', $community);

        $spaceGroups = SpaceTree::for($community, $request->user());

        // Recent resources: only from the resources spaces that the member can see (ADR 0015)
        $resourcesSpaceIds = $spaceGroups
            ->flatMap(fn (SpaceGroup $group) => $group->spaces)
            ->filter(fn (Space $space) => $space->type === KnowiiSpaceType::Resources)
            ->map(fn (Space $space) => $space->id)
            ->values()
            ->all();
        $recentResources = $community->recentResources($resourcesSpaceIds);
        // "Next events" (#1514): the first upcoming events of the events spaces of the navigation of the member
        $nextEvents = UpcomingEvents::next($community, $request->user(), $spaceGroups);

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Show', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->loadCount('users')->toResource()->withLargeFields(),

            'recentResources' => $recentResources->toResourceCollection(),
            // The answers of the member and the places load once for the list (#1515)
            'nextEvents' => EventAnswers::forList($nextEvents, $request->user(), $community, static fn (): array => $nextEvents->toResourceCollection()->resolve($request)),
            'spaceGroups' => $spaceGroups->toResourceCollection(),

            'permissions' => $this->getCommunityPermissions($community),
        ]);
    }

    /**
     * The public page of a community (#1485, ADR 0044), for a visitor and for a signed-in person who is not a member. It
     * exists only when the community has a public space that is not a chat space: any other community answers 404 to a
     * visitor (the sign-in page) and 403 to an outsider, as before, now with the page without access (#1561). Its props
     * are cut here, on the server: the community without its member count, rights and settings, the public spaces without
     * any chat space, the newest public posts, the next events of the public events spaces. No team, no member list, no
     * permission.
     */
    private function showPublic(Request $request, Community $community): Response
    {
        NoAccess::ensurePublicPage($request, $community);
        $nextEvents = UpcomingEvents::next($community, $request->user());

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by Pages/Communities/Public.tsx
        return Jetstream::inertia()->render($request, 'Communities/Public', [
            'community' => $community->toResource(),
            'spaceGroups' => PublicCommunity::tree($community, $request->user())->toResourceCollection(),
            'newestPosts' => PublicCommunity::newestPosts($community)->toResourceCollection(NewestPostResource::class),
            // "Next events" (#1514): the first upcoming events of the public events spaces. The resource cuts the meeting
            // address and the place for a reader who is not a member
            'nextEvents' => EventAnswers::forList($nextEvents, $request->user(), $community, static fn (): array => $nextEvents->toResourceCollection()->resolve($request)),
        ]);
    }

    /**
     * The details page of a community (#1135): what it is, its team, what moderators can do, and the controls of the
     * current user. Same access as the community page.
     */
    public function about(Request $request, string $slug): Response
    {
        $community = Community::where('slug', $slug)->firstOrFail();

        NoAccess::authorizeView($request, $community, $community, KnowiiNoAccessKind::Community);

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by Pages/Communities/About.tsx
        return Jetstream::inertia()->render($request, 'Communities/About', [
            'community' => $community->loadCount('users')->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'team' => CommunityMemberQuery::team($community)->limit(self::MAX_TEAM_MEMBERS)->get()->toResourceCollection(MemberWithRoleResource::class),
            'permissions' => $this->getCommunityPermissions($community),
            'currentRole' => $this->currentRole($request, $community),
        ]);
    }

    /**
     * Show the community settings screen.
     */
    public function settings(Request $request, string $slug): Response
    {
        $community = (new Community)->where('slug', $slug)->firstOrFail();

        // Owner and admins; moderators see the parts that their rights open: spaces (#1240), members (#1242)
        abort_unless(Gate::any(['manage', 'manageSpaces', 'addCommunityMember'], $community), 403);

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Settings', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),

            'permissions' => $this->getCommunityPermissions($community),
            // The member management screen offers only the roles below this one (#1242)
            'currentRole' => $this->currentRole($request, $community),
        ]);
    }

    /**
     * The role of the current user in the community: owner, admin, moderator or member.
     */
    private function currentRole(Request $request, Community $community): string
    {
        /** @var User $user */
        $user = $request->user();

        return CommunityRoles::roleName($user, $community) ?? 'member';
    }

    /**
     * Get the permissions for a community.
     *
     *
     * @return array<string,bool>
     */
    private function getCommunityPermissions(Community $community): array
    {
        // WARNING: The props passed here must remain aligned with the props defined in community.schema.ts
        return [
            'canManageCommunity' => Gate::check('manage', $community),
            'canManageSpaces' => Gate::check('manageSpaces', $community),
            'canManageModeratorPermissions' => Gate::check('manageModeratorPermissions', $community),
            'canManageJoinPolicy' => Gate::check('manageJoinPolicy', $community),
            'canManageIndexing' => Gate::check('manageIndexing', $community),
            'canUpdateCommunity' => Gate::check('update', $community),
            'canDeleteCommunity' => Gate::check('delete', $community),
            'canTransferOwnership' => Gate::check('transferOwnership', $community),

            'canAddCommunityMembers' => Gate::check('addCommunityMember', $community),
            'canUpdateCommunityMembers' => Gate::check('updateCommunityMember', $community),
            'canRemoveCommunityMembers' => Gate::check('removeCommunityMember', $community),

            'canManageAccessGroups' => Gate::check('manageAccessGroups', $community),
            'canManageAccessGroupMembers' => Gate::check('manageAccessGroupMembers', $community),
            'canViewAccessGroups' => Gate::check('viewAccessGroups', $community),
            // The entitlement keys that give an access group (#1386, ADR 0047): a listed community, and the people who manage the groups
            'canManageEntitlementRules' => OidcEntitlements::allows($community) && Gate::check('manageAccessGroups', $community),
        ];
    }
}
