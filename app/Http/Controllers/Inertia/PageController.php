<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\Page;
use App\Support\CourseProgress;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

/**
 * A page of a page space (#1179), or a lesson of a course space (#1188), with links to the previous and the next one.
 * Sections are headings: they have no page, and the navigation skips them.
 */
class PageController extends Controller
{
    public function show(Request $request, string $communitySlug, string $spaceSlug, string $pageSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->whereIn('type', [KnowiiSpaceType::Pages, KnowiiSpaceType::Course])->firstOrFail();

        Gate::authorize('view', $space);

        $rows = $space->pages()->get(['id', 'cuid', 'kind', 'title', 'slug', 'position']);
        $pages = $rows->reject(fn (Page $page) => $page->isSection())->values();
        $index = $pages->search(fn (Page $page) => $page->slug === $pageSlug);
        $found = $index === false ? null : $pages->get($index);
        abort_if($found === null, 404);

        // The section of the page: the last section heading before it (#1188)
        $section = $rows->takeUntil(fn (Page $row) => $row->id === $found->id)->filter(fn (Page $row) => $row->isSection())->last();

        $page = $space->pages()->with('author')->findOrFail($found->id);
        $neighbour = static fn (?Page $other) => $other === null ? null : ['title' => $other->title, 'slug' => $other->slug];

        // Lessons of a course: whether the member marked it as done, and their progress in the course (#1304)
        $user = $request->user();
        $progress = null;
        if ($space->type === KnowiiSpaceType::Course && $user !== null && Gate::check('trackProgress', $space)) {
            $course = CourseProgress::for($user, $space);
            $progress = ['done' => in_array($page->cuid, $course['completed_lessons'], true), 'course' => $course];
        }

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
            'section' => $section?->title,
            'progress' => $progress,
            'permissions' => [
                'canManagePages' => Gate::check('managePages', $space),
            ],
        ]);
    }
}
