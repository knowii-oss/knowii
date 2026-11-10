<?php

namespace App\Http\Controllers\Inertia;

use App\Constants;
use App\Enums\KnowiiSpaceType;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Community;
use App\Models\Space;
use App\Models\User;
use App\Support\Pagination;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class SpaceController extends Controller
{
    // WARNING: aligned with POSTS_PER_PAGE (constants.ts)
    public const int POSTS_PER_PAGE = 20;

    // WARNING: aligned with RESOURCES_PER_PAGE (constants.ts)
    public const int RESOURCES_PER_PAGE = 24;

    /**
     * Show a space of a community.
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->firstOrFail();

        Gate::authorize('view', $space);

        JsonResource::withoutWrapping();

        // Discussion spaces: the first page of posts (the page loads more through the API)
        $posts = null;
        if ($space->type === KnowiiSpaceType::Discussion) {
            $page = (new Pagination(self::POSTS_PER_PAGE, 0))->apply($space->posts()->with(['author', 'space', 'reactions'])->withCount('comments')->forSpacePage());
            $posts = [
                'items' => $page['items']->toResourceCollection(),
                'pagination' => $page['metadata']['pagination'],
            ];
        }

        // Resources spaces: the first page of resources, newest first (ADR 0015)
        $resources = null;
        if ($space->type === KnowiiSpaceType::Resources) {
            $page = (new Pagination(self::RESOURCES_PER_PAGE, 0))->apply($space->resources()->with(['resource', 'curator'])->newestFirst());
            $resources = [
                'items' => $page['items']->toResourceCollection(),
                'pagination' => $page['metadata']['pagination'],
            ];
        }

        // Chat spaces: the newest messages, oldest first, and where the unread ones start (#1185)
        $chat = $space->type === KnowiiSpaceType::Chat ? self::chat($space, $request->user()) : null;

        // Page spaces and course spaces: the titles of the pages, lessons and sections, in their order (#1179, #1188)
        $pages = $space->type->holdsPages() ? $space->pages()->get()->toResourceCollection() : null;

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Spaces/Show.tsx)
        return Jetstream::inertia()->render($request, 'Spaces/Show', [
            'posts' => $posts,
            'resources' => $resources,
            'pages' => $pages,
            'chat' => $chat,
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'permissions' => [
                'canManageSpaces' => Gate::check('manageSpaces', $community),
                'canContribute' => Gate::check('contribute', $space),
                'canModerate' => Gate::check('moderate', $space),
                'canManagePages' => Gate::check('managePages', $space),
                // Members react to and comment what they can read
                'canReact' => $request->user()?->belongsToCommunity($community) ?? false,
            ],
        ]);
    }

    /**
     * @return array{messages: mixed, has_more: bool, first_unread_cuid: string|null}
     */
    private static function chat(Space $space, ?User $user): array
    {
        $items = $space->chatMessages()->whereNull('parent_id')->with(['author', 'reactions'])
            ->orderByDesc('id')->limit(Constants::CHAT_MESSAGES_PER_PAGE + 1)->get();
        $page = $items->take(Constants::CHAT_MESSAGES_PER_PAGE)->reverse()->values();

        $firstUnread = null;
        if ($user !== null && $user->belongsToCommunity($space->community)) {
            $value = ChatRead::query()->where('user_id', $user->id)->where('space_id', $space->id)->value('last_read_message_id');
            $lastRead = is_numeric($value) ? (int) $value : 0;
            $firstUnread = $page->first(fn (ChatMessage $message) => $message->id > $lastRead && $message->author_id !== $user->id)?->cuid;
        }

        return [
            'messages' => $page->toResourceCollection(),
            'has_more' => $items->count() > Constants::CHAT_MESSAGES_PER_PAGE,
            'first_unread_cuid' => $firstUnread,
        ];
    }
}
