<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\FollowsThreads;
use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiFollowReason;
use App\Enums\KnowiiFollowState;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\ChatMessage;
use App\Models\NotificationFollow;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Support\NotificationAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The follows of a member (#1475, ADR 0042, design 2.2). One row per member and thread, with a state of three values:
 * `following`, `muted` and `stopped`. Only a member of the community who can view the space follows its threads: the
 * rule of ADR 0044 is that only members are ever told.
 */
final class FollowThreads implements FollowsThreads
{
    final public function state(User $user, Post|ChatMessage $thread): ?NotificationFollow
    {
        $this->authorize($user, $thread);

        return $this->find($user, $thread);
    }

    final public function follow(User $user, Post|ChatMessage $thread): NotificationFollow
    {
        $this->authorize($user, $thread);

        $follow = $this->find($user, $thread);
        if ($follow?->state === KnowiiFollowState::Following) {
            return $follow;
        }

        return $this->set($user, $thread, KnowiiFollowState::Following, KnowiiFollowReason::Manual);
    }

    final public function stopFollowing(User $user, Post|ChatMessage $thread): NotificationFollow
    {
        $this->authorize($user, $thread);

        return $this->set($user, $thread, KnowiiFollowState::Stopped, KnowiiFollowReason::Manual);
    }

    final public function mute(User $user, Post|ChatMessage $thread): NotificationFollow
    {
        $this->authorize($user, $thread);

        $follow = $this->set($user, $thread, KnowiiFollowState::Muted, KnowiiFollowReason::Manual);
        app(PrunesNotifications::class)->forMutedThread($user, $thread);

        return $follow;
    }

    final public function followByWriting(User $user, Post|ChatMessage $thread, KnowiiFollowReason $reason): void
    {
        if (Notify::isSilenced() || ($thread instanceof Post && $thread->isDeleted()) || ($thread instanceof ChatMessage && ($thread->isDeleted() || $thread->parent_id !== null))) {
            return;
        }
        if (! $user->belongsToCommunity($thread->community)) {
            return;
        }
        // A row that exists says what the member chose: it stays (stopped by hand, muted, or following already)
        if ($this->find($user, $thread) !== null) {
            return;
        }

        try {
            $this->create($user, $thread, KnowiiFollowState::Following, $reason);
        } catch (UniqueConstraintViolationException) {
            // Another request wrote the row first: it wins
        }
    }

    /**
     * The member is a member of the community, can view the space, and the thread is a thread that someone follows: a
     * post of a discussion space, or a first-level message of a chat space, not a marker
     *
     * @throws AuthorizationException
     * @throws BusinessException
     */
    private function authorize(User $user, Post|ChatMessage $thread): void
    {
        $space = $thread->space ?? throw new BusinessException('Only a post or a message of a chat space has a thread');
        $this->assertThread($thread, $space);

        if (! NotificationAccess::canView($user, $thread->community, $space->loadMissing(['accessGroups', 'spaceGroup.accessGroups']))) {
            throw new AuthorizationException('Only the members who can view the space follow its threads');
        }
    }

    /**
     * @throws BusinessException
     */
    private function assertThread(Post|ChatMessage $thread, Space $space): void
    {
        if ($thread->isDeleted()) {
            // A marker gives no notification and has no follows (ADR 0043)
            throw new BusinessException('This item was deleted');
        }
        if ($thread instanceof Post && $space->type !== KnowiiSpaceType::Discussion) {
            throw new BusinessException('Posts can only be followed in discussion spaces');
        }
        if ($thread instanceof ChatMessage && ($space->type !== KnowiiSpaceType::Chat || $thread->isDirectMessage())) {
            throw new BusinessException('Only a message of a chat space has a thread');
        }
        if ($thread instanceof ChatMessage && $thread->parent_id !== null) {
            throw new BusinessException('Threads start at a first-level message: use the first message of the thread');
        }
    }

    private function find(User $user, Post|ChatMessage $thread): ?NotificationFollow
    {
        return NotificationFollow::query()->where('user_id', $user->id)->where($this->column($thread), $thread->id)->first();
    }

    private function set(User $user, Post|ChatMessage $thread, KnowiiFollowState $state, KnowiiFollowReason $reason): NotificationFollow
    {
        $follow = $this->find($user, $thread);
        if ($follow === null) {
            try {
                return $this->create($user, $thread, $state, $reason);
            } catch (UniqueConstraintViolationException) {
                // Another request wrote the row first: the choice of this one wins
                $follow = $this->find($user, $thread) ?? throw new BusinessException('The follow could not be saved');
            }
        }

        $follow->update(['state' => $state, 'reason' => $reason]);

        return $follow;
    }

    private function create(User $user, Post|ChatMessage $thread, KnowiiFollowState $state, KnowiiFollowReason $reason): NotificationFollow
    {
        return NotificationFollow::create([
            'user_id' => $user->id,
            'community_id' => $thread->community_id,
            $this->column($thread) => $thread->id,
            'state' => $state,
            'reason' => $reason,
        ]);
    }

    /**
     * @return 'post_id'|'chat_message_id'
     */
    private function column(Post|ChatMessage $thread): string
    {
        return $thread instanceof Post ? 'post_id' : 'chat_message_id';
    }
}
