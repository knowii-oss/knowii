<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Resources\MemberProfileResource;
use App\Models\Community;
use App\Models\User;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class MemberController extends Controller
{
    /**
     * The profile of a member, as the other members of the community see it (mentions link here).
     */
    public function show(Request $request, string $communitySlug, string $username): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        Gate::authorize('view', $community);

        $member = User::with('profile')->whereRaw('lower(username) = ?', [mb_strtolower($username)])->firstOrFail();
        abort_unless($member->belongsToCommunity($community), 404);

        JsonResource::withoutWrapping();

        // Direct messages (#1186): what the current member can do with this member
        $viewer = $request->user();
        $isSelf = $viewer !== null && $viewer->is($member);
        $blockedByMe = $viewer !== null && ! $isSelf && $viewer->blockedUsers()->whereKey($member->id)->exists();

        // WARNING: aligned with the props of Pages/Members/Show.tsx
        return Jetstream::inertia()->render($request, 'Members/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'member' => $member->toResource(MemberProfileResource::class),
            'messaging' => [
                'isSelf' => $isSelf,
                'blockedByMe' => $blockedByMe,
                'canMessage' => $viewer !== null && ! $isSelf && $viewer->belongsToCommunity($community)
                    && $community->allowsDirectMessages() && ! $viewer->hasBlockWith($member),
            ],
        ]);
    }
}
