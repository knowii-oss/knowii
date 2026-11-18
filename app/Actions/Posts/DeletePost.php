<?php

namespace App\Actions\Posts;

use App\Contracts\Content\LeavesMarkers;
use App\Contracts\Posts\DeletesPosts;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * The author or a moderator deletes a post (#1565, ADR 0050). A post that has a comment of other people (not a marker,
 * not written by the person who deletes, a deleted account included) stays as a marker, with every comment: the same
 * marker as the account deletion (`LeavesMarkers`, ADR 0043). Any other post goes with its comments, reactions and
 * mentions (cascade).
 *
 * With `delete_comments`, a person who moderates the space deletes the post and its whole discussion, a marker too
 * (`PostPolicy::deleteWithComments`).
 */
class DeletePost implements DeletesPosts
{
    final public function delete(User $user, Post $post, array $input = []): ?Post
    {
        Log::info('Processing request to delete a post', ['post' => $post->cuid, 'by' => $user->cuid]);

        $gate = Gate::forUser($user);
        if ($gate->none(['delete', 'deleteWithComments'], $post)) {
            throw new AuthorizationException;
        }

        /** @var array{delete_comments?: bool|int|string} $validated */
        $validated = Validator::make($input, ['delete_comments' => ['sometimes', 'boolean']])->validate();
        $withComments = filter_var($validated['delete_comments'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $gate->authorize($withComments ? 'deleteWithComments' : 'delete', $post);

        // Built before the delete: the event names the space of the post. A marker is an update of the post: the open
        // post page loads it again and shows the marker, the lists drop it
        $updated = SpaceContentChanged::forPost(SpaceContentChanged::POST_UPDATED, $post, $user->cuid);
        $deleted = SpaceContentChanged::forPost(SpaceContentChanged::POST_DELETED, $post, $user->cuid);

        $marker = DB::transaction(static function () use ($user, $post, $withComments): bool {
            $markers = app(LeavesMarkers::class);
            if (! $withComments && $markers->postsAnsweredByOthers([$post->id], $user) !== []) {
                $markers->markPosts([$post->id]);

                return true;
            }

            $post->delete();

            return false;
        });

        event($marker ? $updated : $deleted);
        Log::info($marker ? 'Post deleted, its marker stays' : 'Post deleted', ['post' => $post->cuid]);

        return $marker ? $post->refresh() : null;
    }
}
