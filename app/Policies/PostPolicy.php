<?php

namespace App\Policies;

use App\Enums\KnowiiModeratorPermission;
use App\Models\Post;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Who can read and change a post (#1174). Writing a new post is SpacePolicy::contribute. Editing, deleting or pinning a
 * post needs the access to its space (SpacePolicy::view).
 */
class PostPolicy
{
    use HandlesAuthorization;

    final public function view(?User $user, Post $post): bool
    {
        return Gate::forUser($user)->check('view', $post->space);
    }

    /**
     * Only the author edits a post, while they are still a member of the community who can read the space of the post
     * (SpacePolicy::view), as for a comment. A marker has no author.
     */
    final public function update(User $user, Post $post): bool
    {
        return ! $post->isDeleted()
            && $post->author_id === $user->id && $user->belongsToCommunity($post->community)
            && Gate::forUser($user)->check('view', $post->space);
    }

    /**
     * The author (who can read the space), or a moderator of the space (SpacePolicy::moderate reads the space too). A
     * marker is never deleted again this way, like a chat message (#1185): only with its comments (deleteWithComments).
     */
    final public function delete(User $user, Post $post): bool
    {
        return ! $post->isDeleted() && ($this->update($user, $post) || Gate::forUser($user)->check('moderate', $post->space));
    }

    /**
     * Delete the post with its whole discussion, the comments of other people too (#1565, ADR 0050): a moderator of the
     * space only, a marker included. The author alone leaves a marker for the comments of other people.
     */
    final public function deleteWithComments(User $user, Post $post): bool
    {
        return Gate::forUser($user)->check('moderate', $post->space);
    }

    /**
     * Members of the community who can read the post can comment it (also in spaces where only moderators post).
     */
    final public function comment(User $user, Post $post): bool
    {
        // The comments of a marker stay, and nobody adds to them (#1344)
        return ! $post->isDeleted()
            && $user->belongsToCommunity($post->community) && Gate::forUser($user)->check('view', $post->space);
    }

    /**
     * Same rule as commenting.
     */
    final public function react(User $user, Post $post): bool
    {
        return $this->comment($user, $post);
    }

    /**
     * Owner, admins, and moderators when the community gives them the "pin posts" right (#1240, ADR 0017). Pinning
     * needs the access to the space of the post, as the other moderator actions (SpacePolicy::moderate, ADR 0025).
     */
    final public function pin(User $user, Post $post): bool
    {
        return ! $post->isDeleted()
            && CommunityRoles::allows($user, $post->community, KnowiiModeratorPermission::PinPosts)
            && Gate::forUser($user)->check('view', $post->space);
    }
}
