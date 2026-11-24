<?php

namespace App\Support;

use App\ApiRoutes;
use App\Enums\KnowiiNoAccessKind;
use App\Exceptions\NoAccessException;
use App\Models\Community;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;

/**
 * The one place that decides what a reader learns of a space that they cannot read (`SpacePolicy::view`), for every route
 * under a space: the API (the binding of `{space}`) and the pages (`forPage()`). It runs before any item of the space is
 * read, so no answer tells anything of what is in the space.
 *
 * 1. A visitor and a signed-in person who is not a member get the answer of a space that does not exist: 404 on the API and
 *    for a request that expects JSON. On a page, a visitor goes to the sign-in page (ADR 0044 point 4) and a person who is
 *    not a member gets the page without access (#1561), for a space that does not exist too.
 * 2. A member of the community who cannot read the space (outside its access groups) gets the refusal (403): they may know
 *    that the space exists, never what is in it.
 */
final class SpaceAccess
{
    /**
     * The binding of `{space}` on the API: the space of the community of the route, when this reader may read it. The
     * explicit bindings run before the implicit ones, so no item of the space is read before this check
     *
     * @throws AuthorizationException
     */
    public static function bindRoute(string $cuid, Route $route): Space
    {
        $parameter = $route->parameter(ApiRoutes::PARAM_COMMUNITY);
        $community = $parameter instanceof Community ? $parameter : (is_string($parameter) ? Community::query()->where('cuid', $parameter)->first() : null);
        if ($community === null) {
            throw (new ModelNotFoundException)->setModel(Community::class, [is_string($parameter) ? $parameter : '']);
        }
        // The community is known: the implicit binding does not read it again
        $route->setParameter(ApiRoutes::PARAM_COMMUNITY, $community);

        return self::readable(request(), $community, $community->spaces()->where('cuid', $cuid)->first(), $cuid);
    }

    /**
     * The community and the space of a page, when this reader may read the space. A community that does not exist answers
     * 404 (the sign-in page for a visitor)
     *
     * @return array{0: Community, 1: Space}
     *
     * @throws AuthorizationException
     */
    public static function forPage(Request $request, string $communitySlug, string $spaceSlug): array
    {
        $community = Community::query()->where('slug', $communitySlug)->firstOrFail();

        return [$community, self::readable($request, $community, $community->spaces()->where('slug', $spaceSlug)->first(), $spaceSlug)];
    }

    /**
     * @throws AuthorizationException
     */
    private static function readable(Request $request, Community $community, ?Space $space, string $identifier): Space
    {
        $reader = $request->user();

        if ($space !== null) {
            // The policies of the space and of its items read the community: it is known
            $space->setRelation('community', $community);
            $response = Gate::forUser($reader)->inspect('view', $space);
            if ($response->allowed()) {
                return $space;
            }
            // A member may learn that the space exists, and nothing of what is in it
            if (PublicCommunity::isMember($reader, $community)) {
                $response->authorize();
            }
        }

        // A visitor and a person who is not a member: the answer of a space that does not exist
        if (NoAccess::appliesTo($request, $community)) {
            throw new NoAccessException($community, KnowiiNoAccessKind::Space);
        }

        throw (new ModelNotFoundException)->setModel(Space::class, [$identifier]);
    }
}
