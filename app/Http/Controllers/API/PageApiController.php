<?php

namespace App\Http\Controllers\API;

use App\Contracts\Pages\CreatesPages;
use App\Contracts\Pages\DeletesPages;
use App\Contracts\Pages\ReordersPages;
use App\Contracts\Pages\UpdatesPages;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Page;
use App\Models\Space;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The pages of a page space (#1179). Members who can read the space read its pages; the owner, the admins and the
 * moderators manage them.
 */
class PageApiController extends Controller
{
    use ApiResponses;

    /**
     * List the pages of a page space, in their order. Lists carry the titles only: get one page for its text.
     */
    final public function index(Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);
        self::assertPageSpace($space);

        return self::success('Pages', null, $space->pages()->get()->toResourceCollection());
    }

    /**
     * Get a page with its Markdown and its HTML.
     */
    final public function show(Community $community, Space $space, Page $page): JsonResponse
    {
        Gate::authorize('view', $space);

        return self::success('Page', null, $page->load('author')->toResource()->withLargeFields());
    }

    /**
     * Add a page at the end of a page space.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title of the page (3 to 200 characters)', required: true, type: 'string', example: 'Start here')]
    #[BodyParameter('body', description: 'Text of the page in Markdown (up to 50 000 characters). Not for a section', required: true, type: 'string', example: 'Welcome! Read these pages in order.')]
    #[BodyParameter('kind', description: 'page (default; a lesson in a course space) or section (a heading for the pages after it, a title only)', required: false, type: 'string', example: 'page')]
    #[BodyParameter('video_url', description: 'Lessons of a course space only: the address of a YouTube or Vimeo video', required: false, type: 'string', format: 'uri', example: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        self::assertPageSpace($space);
        $page = app(CreatesPages::class)->create($request->user() ?? throw new AuthenticationException, $space, $request->only(['title', 'body', 'kind', 'video_url']));

        return self::created($page->load('author')->toResource()->withLargeFields(), 'Page created');
    }

    /**
     * Change the title or the text of a page. The slug stays.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title of the page (3 to 200 characters)', required: false, type: 'string')]
    #[BodyParameter('body', description: 'Text of the page in Markdown', required: false, type: 'string')]
    #[BodyParameter('video_url', description: 'Lessons of a course space only: a YouTube or Vimeo address; empty removes the video', required: false, type: 'string', format: 'uri')]
    final public function update(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        $page = app(UpdatesPages::class)->update($request->user() ?? throw new AuthenticationException, $page, $request->only(['title', 'body', 'video_url']));

        return self::success('Page updated', null, $page->load('author')->toResource()->withLargeFields());
    }

    /**
     * Delete a page.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Page $page): JsonResponse
    {
        app(DeletesPages::class)->delete($request->user() ?? throw new AuthenticationException, $page);

        return self::success('Page deleted');
    }

    /**
     * Put the pages of a page space in a new order: the cuids of every page of the space, once.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('pages', description: 'The cuids of every page of the space, in the new order', required: true, type: 'array', example: ['pgcuid1', 'pgcuid2'])]
    final public function reorder(Request $request, Community $community, Space $space): JsonResponse
    {
        self::assertPageSpace($space);
        app(ReordersPages::class)->reorder($request->user() ?? throw new AuthenticationException, $space, $request->only(['pages']));

        return self::success('Pages reordered', null, $space->pages()->get()->toResourceCollection());
    }

    private static function assertPageSpace(Space $space): void
    {
        if (! $space->type->holdsPages()) {
            throw new BusinessException('Pages only exist in page spaces and course spaces');
        }
    }
}
