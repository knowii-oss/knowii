<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiSpaceType;
use App\Http\Resources\MemberWithRoleResource;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityMemberQuery;
use App\Support\CommunityRoles;
use App\Support\SpaceTree;
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

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Show', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->loadCount('users')->toResource()->withLargeFields(),

            'recentResources' => $recentResources->toResourceCollection(),
            'spaceGroups' => $spaceGroups->toResourceCollection(),

            'permissions' => $this->getCommunityPermissions($community),
        ]);
    }

    /**
     * The details page of a community (#1135): what it is, its team, what moderators can do, and the controls of the
     * current user. Same access as the community page.
     */
    public function about(Request $request, string $slug): Response
    {
        $community = Community::where('slug', $slug)->firstOrFail();

        Gate::authorize('view', $community);

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
            'canUpdateCommunity' => Gate::check('update', $community),
            'canDeleteCommunity' => Gate::check('delete', $community),

            'canAddCommunityMembers' => Gate::check('addCommunityMember', $community),
            'canUpdateCommunityMembers' => Gate::check('updateCommunityMember', $community),
            'canRemoveCommunityMembers' => Gate::check('removeCommunityMember', $community),
        ];
    }
}
