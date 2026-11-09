<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\Page;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * A page of a page space (#1179), with links to the previous and the next page.
 */
class PageController extends Controller
{
    public function show(Request $request, string $communitySlug, string $spaceSlug, string $pageSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->where('type', KnowiiSpaceType::Pages)->firstOrFail();

        Gate::authorize('view', $space);

        $pages = $space->pages()->get(['id', 'cuid', 'title', 'slug', 'position']);
        $index = $pages->search(fn (Page $page) => $page->slug === $pageSlug);
        $found = $index === false ? null : $pages->get($index);
        abort_if($found === null, 404);

        $page = $space->pages()->with('author')->findOrFail($found->id);
        $neighbour = static fn (?Page $other) => $other === null ? null : ['title' => $other->title, 'slug' => $other->slug];

        JsonResource::withoutWrapping();

        // WARNING: aligned with the props of the page (Pages/SpacePages/Show.tsx)
        return Jetstream::inertia()->render($request, 'SpacePages/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'page' => $page->toResource()->withLargeFields(),
            'previous' => $neighbour($pages->get($index - 1)),
            'next' => $neighbour($pages->get($index + 1)),
            'position' => ['current' => $index + 1, 'total' => $pages->count()],
            'permissions' => [
                'canManagePages' => Gate::check('managePages', $space),
            ],
        ]);
    }
}
