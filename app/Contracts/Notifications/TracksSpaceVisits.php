<?php

namespace App\Contracts\Notifications;

use App\Models\Community;
use App\Models\Space;
use App\Models\User;

/**
 * The read marker behind the dots of the navigation (#1476, ADR 0048, design 5.2.3): one visit per member and space, and
 * the date when content was last added to a space. Only a member of the community who can view the space has a visit: a
 * visitor and a signed-in person who is not a member never get a row.
 */
interface TracksSpaceVisits
{
    /**
     * The member visits the space: the row is replaced with now. Returns false, and writes nothing, for a person who is
     * not a member of the community or who cannot view the space.
     */
    public function visit(User $user, Space $space): bool;

    /**
     * The member wrote content in the space (a post, a comment, a page, a resource): the author has seen the space as of
     * now, and when the content is new content, the space has new content as of now too (a comment of a resource is not).
     */
    public function wroteIn(User $user, Space $space, bool $newContent = true): void;

    /**
     * A member left the community or was removed: their visits of its spaces go
     */
    public function forget(User $user, Community $community): void;
}
