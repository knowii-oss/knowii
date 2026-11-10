<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Contracts\Realtime\ReadsPresenceChannels;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Space;
use App\Models\User;
use App\Support\Presence;
use App\Traits\ApiResponses;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Who is online now (#1187, ADR 0027), from the presence channels of the WebSocket server. The pages of a member who
 * hides their online status read these lists, because they never join the channels; API clients too. Members only.
 */
class OnlineMemberApiController extends Controller
{
    use ApiResponses;

    /**
     * The members of the community who are online now and show it, by name (100 at most).
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    final public function community(Request $request, Community $community, ReadsPresenceChannels $channels): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community see who is online');
        }

        $channel = str_replace(Constants::WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $community->cuid, Constants::WS_CHANNEL_COMMUNITY_ONLINE);

        $members = self::visibleUsers($community, $channels->memberCuids($channel));

        return self::success('Online members', null, $members->map(fn (User $member) => Presence::info($member))->values()->all());
    }

    /**
     * The members who are in the chat space now and show it, by name (100 at most).
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    final public function space(Request $request, Community $community, Space $space, ReadsPresenceChannels $channels): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community see who is online');
        }
        Gate::authorize('view', $space);

        if ($space->type !== KnowiiSpaceType::Chat) {
            throw new BusinessException('Only chat spaces show who is in them');
        }

        $channel = str_replace(
            [Constants::WS_CHANNEL_COMMUNITY_SPACE_PARAM_COMMUNITY_CUID, Constants::WS_CHANNEL_COMMUNITY_SPACE_PARAM_SPACE_CUID],
            [$community->cuid, $space->cuid],
            Constants::WS_CHANNEL_COMMUNITY_SPACE_PRESENCE,
        );

        $members = self::visibleUsers($community, $channels->memberCuids($channel))
            ->filter(fn (User $member) => $member->can('view', $space));

        return self::success('Members in the space', null, $members->map(fn (User $member) => Presence::info($member))->values()->all());
    }

    /**
     * The channel can hold a member who hid their status or left the community after joining: check both again.
     *
     * @param  list<string>  $cuids
     * @return Collection<int, User>
     */
    private static function visibleUsers(Community $community, array $cuids): Collection
    {
        if ($cuids === []) {
            return new Collection;
        }

        return User::query()
            ->whereIn('cuid', array_unique($cuids))
            ->where('show_online_status', true)
            ->where(fn (Builder $query) => $query
                ->where('id', $community->owner_id)
                ->orWhereHas('communities', fn (Builder $communities) => $communities->whereKey($community->id)))
            // The space check reads the roles and the access groups (SpacePolicy::view)
            ->with(['communities', 'accessGroups'])
            ->orderBy('name')
            ->limit(Constants::MAX_ONLINE_MEMBERS_LISTED)
            ->get();
    }
}
