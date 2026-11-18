<?php

namespace App\Enums;

/**
 * The kind of page that a signed-in person who is not a member may not read (#1561): it chooses the reason of the page
 * without access ("This space is for the members of ..." or "This page is for the members of ...")
 *
 * WARNING: Must remain in sync with the one defined in community.schema.ts
 */
enum KnowiiNoAccessKind: string
{
    /** A space, or a post, a page, a lesson or a resource of a space */
    case Space = 'space';
    /** A page of the community itself: its home, its details page, the page of a member */
    case Community = 'community';
}
