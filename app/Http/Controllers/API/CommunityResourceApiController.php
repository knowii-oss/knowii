<?php

namespace App\Http\Controllers\API;

use App\Contracts\Resources\CreatesCommunityResources;
use App\Enums\KnowiiCaptureStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Jobs\CaptureCommunityResource;
use App\Models\Community;
use App\Models\CommunityResource;
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
     * captured content: get one resource for it.
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
     * Get a resource of a resources space, with its large field: the captured Markdown (ADR 0018).
     */
    final public function show(Community $community, Space $space, CommunityResource $resource): JsonResponse
    {
        Gate::authorize('view', $space);

        return self::success('Resource', null, $resource->load(['resource', 'curator', 'capture', 'space'])->toResource()->withLargeFields());
    }

    /**
     * Add a link to a resources space. The posting policy of the space decides who can add one. The answer comes at once
     * with the capture status "pending": a queued job captures the page and the space channel announces each change
     * (ADR 0018).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the resource in the community (3 to 64 characters). Empty: the title of the page', required: false, type: 'string', example: 'How to take smart notes')]
    #[BodyParameter('url', description: 'Absolute http or https URL of the page', required: true, type: 'string', format: 'uri', example: 'https://example.com/some-article')]
    #[BodyParameter('level', description: 'beginner, intermediate, advanced, expert or unknown', required: true, type: 'string', example: 'beginner')]
    #[BodyParameter('description', description: 'Why the resource is useful (up to 255 characters)', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $resource = app(CreatesCommunityResources::class)->create(
            $request->user() ?? throw new AuthenticationException,
            $space,
            $request->only(['name', 'description', 'level', 'url'])
        );

        return self::created(
            $resource->load(['resource', 'curator', 'capture', 'space'])->toResource()->withLargeFields(),
            'Resource created successfully'
        );
    }

    /**
     * Start the capture of a resource again (ADR 0018): the curator, or a member who manages the spaces. Refused while
     * a capture runs. The answer comes at once with the status "pending".
     */
    final public function capture(Community $community, Space $space, CommunityResource $resource): JsonResponse
    {
        Gate::authorize('capture', $resource);

        if (in_array($resource->capture_status, [KnowiiCaptureStatus::Pending, KnowiiCaptureStatus::Capturing], true)) {
            throw new BusinessException('The capture of this resource is already running');
        }

        $resource->update(['capture_status' => KnowiiCaptureStatus::Pending, 'capture_failure_reason' => null]);
        CaptureCommunityResource::dispatch($resource);

        return self::success('Capture started', null, $resource->load(['resource', 'curator', 'capture', 'space'])->toResource());
    }
}
