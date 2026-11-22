<?php

namespace App\Http\Controllers\Inertia;

use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\InvitationImport;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InvitationImports\InvitationImports;
use App\Support\MemberTagsOfMembers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * The pages of the invitations from a file (#1390, ADR 0061): the upload with the earlier imports, and one import (its
 * preview, its send, its log). The lists load through the API. A person without the right to add members gets the page
 * without access, as the settings.
 */
class InvitationImportController extends Controller
{
    public function index(Request $request, string $communitySlug): Response
    {
        $community = $this->community($communitySlug);

        return Jetstream::inertia()->render($request, 'Communities/InvitationImports/Index', $this->props($request, $community));
    }

    public function show(Request $request, string $communitySlug, string $importCuid): Response
    {
        $community = $this->community($communitySlug);
        $import = InvitationImport::query()->where('community_id', $community->id)->where('cuid', $importCuid)->firstOrFail();
        InvitationImports::assertAvailable($import);
        InvitationImports::prepare([$import], $community);

        return Jetstream::inertia()->render($request, 'Communities/InvitationImports/Show', [
            ...$this->props($request, $community),
            'invitationImport' => $import->toResource(),
        ]);
    }

    private function community(string $slug): Community
    {
        $community = Community::query()->where('slug', $slug)->firstOrFail();
        abort_unless(Gate::check('addCommunityMember', $community), 403);
        JsonResource::withoutWrapping();

        return $community;
    }

    /**
     * WARNING: aligned with the props of the two pages
     *
     * @return array{community: JsonResource, currentRole: string, permissions: array<string, bool>, accessGroups: array<int, array{cuid: string, name: string}>, memberTags: ResourceCollection|array{}, userCuid: string}
     */
    private function props(Request $request, Community $community): array
    {
        /** @var User $user */
        $user = $request->user();
        $canGiveGroups = Gate::check('manageAccessGroupMembers', $community);
        $canGiveTags = Gate::check('assignMemberTags', $community);

        return [
            'community' => $community->toResource(),
            'currentRole' => CommunityRoles::roleName($user, $community) ?? 'member',
            'permissions' => [
                'canAddCommunityMembers' => true,
                'canManageAccessGroupMembers' => $canGiveGroups,
                'canAssignMemberTags' => $canGiveTags,
                // Delete the record of a sent import, discard the preview of another manager
                'canDeleteInvitationImports' => CommunityRoles::isOwnerOrAdmin($user, $community),
            ],
            // The choices of the defaults (InvitationGrantsPicker)
            'accessGroups' => $canGiveGroups
                ? AccessGroup::query()->where('community_id', $community->id)->orderByRaw('lower(name)')->orderBy('id')->get(['cuid', 'name'])
                    ->map(static fn (AccessGroup $group): array => ['cuid' => $group->cuid, 'name' => $group->name])->values()->all()
                : [],
            'memberTags' => $canGiveTags ? MemberTagsOfMembers::ofCommunity($community, $user)->toResourceCollection() : [],
            'userCuid' => $user->cuid,
        ];
    }
}
