<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Notifications\TracksSpaceVisits;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Support\CommentThread;
use App\Support\Markdown;
use App\Support\SpaceAccess;
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
        // The access to the space comes first: the type of a space that the reader cannot read never leaves
        [$community, $space] = SpaceAccess::forPage($request, $communitySlug, $spaceSlug);
        abort_unless($space->type === KnowiiSpaceType::Resources, 404);

        // A member who opens the space has visited it: the dot of the navigation goes (#1476). Before the navigation is built
        if ($request->user() !== null) {
            app(TracksSpaceVisits::class)->visit($request->user(), $space);
        }

        // The data of the capture is for the members of the community only (ADR 0018), also in a public space: the same
        // cut as the API read (CommunityResourceApiController::show)
        $isMember = $request->user()?->belongsToCommunity($community) ?? false;
        $resource = $space->resources()->where('slug', $resourceSlug)->with($isMember ? ['curator', 'resource', 'capture', 'space'] : ['curator', 'resource', 'space'])->firstOrFail();

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Resources/Show.tsx)
        return Jetstream::inertia()->render($request, 'Resources/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            // Not the large fields: the captured Markdown comes rendered, in 'article'
            'resource' => $resource->toResource(),
            // The captured article (#1247): members of the community only, never visitors (ADR 0018)
            'article' => $isMember ? $this->article($resource) : null,
            // Comments on the resource (#1034)
            'comments' => CommentThread::for($resource)->toResourceCollection(),
            'permissions' => [
                'canComment' => Gate::check('comment', $resource),
                'canModerate' => Gate::check('moderate', $space),
                // "Capture again" (ADR 0018)
                'canCapture' => Gate::check('capture', $resource),
                // Change the type and the level (#1246)
                'canUpdate' => Gate::check('update', $resource),
                'canDelete' => Gate::check('delete', $resource),
            ],
        ]);
    }

    /**
     * The captured article of an article resource, rendered on the server (ADR 0013: raw HTML escaped). Null for other
     * types and without a capture. For the members of the community only: the caller loads the capture for them alone.
     *
     * @return array{html: string, word_count: int, reading_time: int, source: string, captured_at: string}|null
     */
    private function article(CommunityResource $resource): ?array
    {
        $capture = $resource->capture;
        if ($capture === null || $resource->type !== KnowiiResourceType::Article) {
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
