<?php

namespace App\Http\Controllers\API;

use App\Contracts\Resources\CreatesTextResources;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Space;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Resources of the resources spaces (ADR 0015). Changes are delegated to the actions (ADR 0006).
 */
class CommunityResourceApiController extends Controller
{
    use ApiResponses;

    /**
     * List the resources of a resources space, newest first. Offset pagination (ADR 0011). Lists never carry the
     * large fields (article Markdown, base64 cover images): get one resource for them.
     */
    #[QueryParameter('limit', description: 'Number of resources (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of resources to skip (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);

        $page = Pagination::fromRequest($request)->apply($space->resources()->with(['resource', 'curator'])->newestFirst());

        return self::success('Resources', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * Add a resource to a resources space, from the URL of a web article. The posting policy of the space decides who can add one.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the resource in the community (3 to 64 characters)', required: true, type: 'string', example: 'How to take smart notes')]
    #[BodyParameter('url', description: 'Absolute URL of the article. Video and streaming platforms are refused', required: true, type: 'string', format: 'uri', example: 'https://example.com/some-article')]
    #[BodyParameter('level', description: 'beginner, intermediate, advanced, expert or unknown', required: true, type: 'string', example: 'beginner')]
    #[BodyParameter('description', description: 'Why the resource is useful (up to 255 characters)', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $resource = app(CreatesTextResources::class)->create(
            $request->user() ?? throw new AuthenticationException,
            $space,
            $request->only(['name', 'description', 'level', 'url'])
        );

        return self::created(
            $resource->load(['resource', 'curator', 'textArticle', 'space'])->toResource()->withLargeFields(),
            'Resource created successfully'
        );
    }
}
