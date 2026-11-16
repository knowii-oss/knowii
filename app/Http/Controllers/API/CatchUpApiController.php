<?php

namespace App\Http\Controllers\API;

use App\Contracts\Notifications\BuildsCatchUp;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The data of the "Catch up" card (#1476, ADR 0048, design 5.5). Members of the community only.
 */
class CatchUpApiController extends Controller
{
    use ApiResponses;

    /**
     * What the member missed since a date, in at most 5 lines, in this order: up to 2 open notifications that need them
     * (`needs_you`), up to 2 announcements (`announcement`: posts of other members in the discussion spaces where only
     * the moderators post, never left out because of their age) and 1 thread with new comments (`thread`: the most new
     * comments, among the posts that the member follows and the spaces at Everything). Each line has a `kind`, a `title`,
     * a plain text `excerpt` built from the live content, the `space`, the `author`, the `date` and the `target` to open
     * the item, like a notification. `has_more` is true when a source holds more than what is shown. The answer never
     * holds a count of what the member missed. Only what the member may read shows, and nothing of a member whom they
     * blocked. `since` is required: a date `YYYY-MM-DD`, not in the future, at most 366 days back.
     *
     * @throws AuthenticationException
     */
    #[QueryParameter('since', description: 'The date to catch up from, YYYY-MM-DD (not in the future, 366 days back at most)', required: true, type: 'string', example: '2026-11-01')]
    final public function show(Request $request, Community $community): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return self::success('Catch up', null, app(BuildsCatchUp::class)->build($user, $community, $request->only(['since'])));
    }
}
