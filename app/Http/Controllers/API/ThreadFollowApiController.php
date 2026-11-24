<?php

namespace App\Http\Controllers\API;

use App\Contracts\Notifications\FollowsThreads;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\NotificationFollow;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the current member chose for a thread (#1475, ADR 0042, design 2.2): a post with its comments, or a first-level
 * chat message with its replies. The state is `following`, `muted` or `stopped` (the member stopped following by hand:
 * writing in the thread again does not follow it again), or null when the member has no row. The reason is `wrote`,
 * `replied` or `manual`. Only a member of the community who can view the space has a state: a space that the person
 * cannot read answers as a space that does not exist (404) for a person who is not a member, and 403 for a member outside
 * its access groups; a person who is not a member gets 403 in a public space. A marker of a deleted item has no state (a
 * business error).
 */
class ThreadFollowApiController extends Controller
{
    use ApiResponses;

    /**
     * The follow state of the current member for a post: `state` and `reason`, both null without a row.
     *
     * @throws AuthenticationException
     */
    final public function showPost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return self::success('Follow state', null, self::present(app(FollowsThreads::class)->state(self::user($request), $post)));
    }

    /**
     * "Follow": the member is told about the new comments of the post. A post that they follow already keeps its reason.
     *
     * @throws AuthenticationException
     */
    final public function followPost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return self::success('Following', null, self::present(app(FollowsThreads::class)->follow(self::user($request), $post)));
    }

    /**
     * "Stop following": the state becomes `stopped`. Writing in the thread again does not follow it again.
     *
     * @throws AuthenticationException
     */
    final public function stopPost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return self::success('Not following', null, self::present(app(FollowsThreads::class)->stopFollowing(self::user($request), $post)));
    }

    /**
     * "Mute this thread": nothing reaches the member from the post, also when someone mentions them. Their open
     * notifications about the post are deleted.
     *
     * @throws AuthenticationException
     */
    final public function mutePost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return self::success('Muted', null, self::present(app(FollowsThreads::class)->mute(self::user($request), $post)));
    }

    /**
     * "Undo" of the mute (#1509): the state and the reason of the thread come back as they were before the first mute (no
     * row again for a thread that had none). The notifications that the mute deleted do not come back. A post that is not
     * muted does not change: the answer is its current state.
     *
     * @throws AuthenticationException
     */
    final public function unmutePost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return self::success('Unmuted', null, self::present(app(FollowsThreads::class)->unmute(self::user($request), $post)));
    }

    /**
     * The follow state of the current member for the thread of a first-level chat message: `state` and `reason`, both
     * null without a row.
     *
     * @throws AuthenticationException
     */
    final public function showMessage(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        return self::success('Follow state', null, self::present(app(FollowsThreads::class)->state(self::user($request), $message)));
    }

    /**
     * "Follow": the member is told about the new replies of the thread.
     *
     * @throws AuthenticationException
     */
    final public function followMessage(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        return self::success('Following', null, self::present(app(FollowsThreads::class)->follow(self::user($request), $message)));
    }

    /**
     * "Stop following": the state becomes `stopped`.
     *
     * @throws AuthenticationException
     */
    final public function stopMessage(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        return self::success('Not following', null, self::present(app(FollowsThreads::class)->stopFollowing(self::user($request), $message)));
    }

    /**
     * "Mute this thread": nothing reaches the member from the thread, also when someone mentions them. Their open
     * notifications about the thread are deleted.
     *
     * @throws AuthenticationException
     */
    final public function muteMessage(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        return self::success('Muted', null, self::present(app(FollowsThreads::class)->mute(self::user($request), $message)));
    }

    /**
     * "Undo" of the mute (#1509): the state and the reason of the thread come back as they were before the first mute (no
     * row again for a thread that had none). The notifications that the mute deleted do not come back. A thread that is
     * not muted does not change: the answer is its current state.
     *
     * @throws AuthenticationException
     */
    final public function unmuteMessage(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        return self::success('Unmuted', null, self::present(app(FollowsThreads::class)->unmute(self::user($request), $message)));
    }

    /**
     * @throws AuthenticationException
     */
    private static function user(Request $request): User
    {
        return $request->user() ?? throw new AuthenticationException;
    }

    /**
     * @return array{state: string|null, reason: string|null}
     */
    private static function present(?NotificationFollow $follow): array
    {
        return ['state' => $follow?->state->value, 'reason' => $follow?->reason->value];
    }
}
