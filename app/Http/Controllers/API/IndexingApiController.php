<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\UpdatesCommunityIndexing;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Whether search engines may index the public spaces of a community (#1487, ADR 0044). The change is delegated to the
 * action (ADR 0006).
 */
class IndexingApiController extends Controller
{
    use ApiResponses;

    /**
     * Let search engines index the public spaces of the community, or not (owner and admins). Off by default. It only
     * matters when the instance allows indexing too. Never for a personal community. Returns the community with its
     * `indexing_enabled` value (read it with GET community, as a member).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('indexing_enabled', description: 'true: search engines may index the public spaces that visitors can read. false (the default): they may not', required: true, type: 'bool', example: true)]
    final public function update(Request $request, Community $community): JsonResponse
    {
        $community = app(UpdatesCommunityIndexing::class)->update($request->user() ?? throw new AuthenticationException, $community, $request->only(['indexing_enabled']));

        return self::success('Indexing updated', null, $community->loadCount('users')->toResource()->withLargeFields());
    }
}
