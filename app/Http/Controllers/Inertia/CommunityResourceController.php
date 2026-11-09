<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Support\CommentThread;
use App\Support\Markdown;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class CommunityResourceController extends Controller
{
    /**
     * Show a resource of a resources space (ADR 0015).
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug, string $resourceSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->where('type', KnowiiSpaceType::Resources)->firstOrFail();

        Gate::authorize('view', $space);

        $resource = $space->resources()->where('slug', $resourceSlug)->with(['curator', 'resource', 'capture', 'space'])->firstOrFail();

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Resources/Show.tsx)
        return Jetstream::inertia()->render($request, 'Resources/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            // Not the large fields: the captured Markdown comes rendered, in 'article'
            'resource' => $resource->toResource(),
            // The captured article (#1247): members of the community only, never visitors (ADR 0018)
            'article' => $this->article($request, $community, $resource),
            // Comments on the resource (#1034)
            'comments' => CommentThread::for($resource)->toResourceCollection(),
            'permissions' => [
                'canComment' => Gate::check('comment', $resource),
                'canModerate' => Gate::check('moderate', $space),
                // "Capture again" (ADR 0018)
                'canCapture' => Gate::check('capture', $resource),
                // Change the type and the level (#1246)
                'canUpdate' => Gate::check('update', $resource),
            ],
        ]);
    }

    /**
     * The captured article of an article resource, rendered on the server (ADR 0013: raw HTML escaped). Null for other
     * types, without a capture, and for readers who are not members of the community.
     *
     * @return array{html: string, word_count: int, reading_time: int, source: string, captured_at: string}|null
     */
    private function article(Request $request, Community $community, CommunityResource $resource): ?array
    {
        $capture = $resource->capture;
        if ($capture === null || $resource->type !== KnowiiResourceType::Article || ! ($request->user()?->belongsToCommunity($community) ?? false)) {
            return null;
        }

        return [
            'html' => Markdown::render($capture->markdown),
            'word_count' => $capture->word_count,
            'reading_time' => $capture->reading_time,
            'source' => $capture->source->value,
            'captured_at' => $capture->captured_at->toIso8601String(),
        ];
    }
}
