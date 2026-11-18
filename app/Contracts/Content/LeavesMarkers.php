<?php

namespace App\Contracts\Content;

use App\Models\User;

/**
 * The one place that turns posts and comments into markers (#1565, ADR 0050): the ordinary delete and the account
 * deletion (ADR 0043) use it, so that their markers are the same. It takes lists of ids, for the chunks of the account
 * deletion, and runs in the transaction of the caller.
 */
interface LeavesMarkers
{
    /**
     * The posts among these that have a comment of other people than $user: a comment that is not a marker and whose
     * author is not $user (a comment of a deleted account counts).
     *
     * @param  list<int>  $postIds
     * @return list<int>
     */
    public function postsAnsweredByOthers(array $postIds, User $user): array;

    /**
     * The comments among these that have a reply of other people than $user: a reply that is not a marker and whose
     * author is not $user (a reply of a deleted account counts).
     *
     * @param  list<int>  $commentIds
     * @return list<int>
     */
    public function commentsAnsweredByOthers(array $commentIds, User $user): array;

    /**
     * The posts become markers: no title, no text, no author, a slug that names nothing (`deleted-{cuid}`), not pinned,
     * `deleted_at` set. Their reactions, mentions, upload usages, notifications and follows go. Their comments stay.
     *
     * @param  list<int>  $postIds
     */
    public function markPosts(array $postIds): void;

    /**
     * The comments become markers: no text, no author, `deleted_at` set. Their reactions, mentions, upload usages and
     * notifications go. Their replies stay.
     *
     * @param  list<int>  $commentIds
     */
    public function markComments(array $commentIds): void;
}
