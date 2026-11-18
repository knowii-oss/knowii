<?php

namespace App\Support;

use App\Enums\KnowiiNoAccessKind;
use App\Exceptions\NoAccessException;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The page without access (#1561): the pages of a community call it in place of `Gate::authorize('view', ...)`. The
 * policy decides, as before. A refusal gets the page without access (`NoAccessException`, still a 403) only for a
 * signed-in person who is not a member, on a web page. A visitor, a member (for example in a space of access groups) and
 * a request that expects JSON get the same answer as before.
 */
final class NoAccess
{
    /**
     * Whether a refusal on a page of this community gets the page without access
     */
    public static function appliesTo(Request $request, Community $community): bool
    {
        return $request->user() !== null
            && ! PublicCommunity::isMember($request->user(), $community)
            && ! $request->expectsJson()
            && ! $request->is('api/*');
    }

    /**
     * `Gate::authorize('view', $subject)`, with the page without access for a signed-in person who is not a member
     */
    public static function authorizeView(Request $request, Community $community, Community|Space|Post $subject, KnowiiNoAccessKind $kind): void
    {
        $response = Gate::inspect('view', $subject);

        if ($response->denied() && self::appliesTo($request, $community)) {
            throw new NoAccessException($community, $kind);
        }

        $response->authorize();
    }

    /**
     * `PublicCommunity::ensureExists()` for the community home: a signed-in person who is not a member of a community
     * without a public page gets the page without access, which then names nothing of the community
     */
    public static function ensurePublicPage(Request $request, Community $community): void
    {
        if (self::appliesTo($request, $community) && ! PublicCommunity::exists($community)) {
            throw new NoAccessException($community, KnowiiNoAccessKind::Community);
        }

        PublicCommunity::ensureExists($community, $request);
    }
}
