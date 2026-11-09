<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Resources\CommunityResource;
use App\Http\Resources\MemberProfileResource;
use App\Http\Resources\SpaceGroupResource;
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

        // WARNING: aligned with the props of Pages/Members/Show.tsx
        return Jetstream::inertia()->render($request, 'Members/Show', [
            'community' => new CommunityResource($community, true),
            'spaceGroups' => SpaceGroupResource::collection(SpaceTree::for($community, $request->user())),
            'member' => new MemberProfileResource($member),
        ]);
    }
}
