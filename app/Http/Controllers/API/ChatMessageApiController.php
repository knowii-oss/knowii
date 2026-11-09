<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Chat\DeletesChatMessages;
use App\Contracts\Chat\MarksChatAsRead;
use App\Contracts\Chat\UpdatesChatMessages;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Space;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The messages of a chat space and their threads (#1185). Cursor pagination for the feed (ADR 0011): the newest
 * messages first, then the older ones before a cursor.
 */
class ChatMessageApiController extends Controller
{
    use ApiResponses;

    /**
     * The first-level messages of a chat space, oldest first in the page. Without `before`, the newest page; with
     * `before` (a message cuid), the page of older messages before it. `metadata.has_more` says whether older ones exist.
     */
    #[QueryParameter('before', description: 'The cuid of a message: return the messages before it', type: 'string')]
    final public function index(Request $request, Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);
        self::assertChatSpace($space);

        $query = $space->chatMessages()->whereNull('parent_id')->with(['author', 'reactions']);
        $before = $request->query('before');
        if (is_string($before) && $before !== '') {
            $cursor = $space->chatMessages()->where('cuid', $before)->value('id');
            $query->where('id', '<', is_numeric($cursor) ? (int) $cursor : 0);
        }

        $items = $query->orderByDesc('id')->limit(Constants::CHAT_MESSAGES_PER_PAGE + 1)->get();
        $hasMore = $items->count() > Constants::CHAT_MESSAGES_PER_PAGE;
        $page = $items->take(Constants::CHAT_MESSAGES_PER_PAGE)->reverse()->values();

        return self::success('Messages', ['has_more' => $hasMore], $page->toResourceCollection());
    }

    /**
     * One message (a live update loads it again).
     */
    final public function show(Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        Gate::authorize('view', $space);

        return self::success('Message', null, $message->load(['author', 'reactions', 'parent'])->toResource());
    }

    /**
     * The thread of a message: the message and its replies, oldest first.
     */
    final public function thread(Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        Gate::authorize('view', $space);
        if ($message->parent_id !== null) {
            throw new BusinessException('Threads start at a first-level message');
        }

        return self::success('Thread', null, [
            'message' => $message->load(['author', 'reactions'])->toResource()->toArray(request()),
            'replies' => $message->replies()->with(['author', 'reactions'])->get()->toResourceCollection()->toArray(request()),
        ]);
    }

    /**
     * Write a message, or a reply in a thread with `parent_cuid`.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Text of the message in Markdown (up to 10 000 characters)', required: true, type: 'string', example: 'Hello everyone!')]
    #[BodyParameter('parent_cuid', description: 'The first message of a thread: the new message is a reply in it', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $message = app(CreatesChatMessages::class)->create($request->user() ?? throw new AuthenticationException, $space, $request->only(['body', 'parent_cuid']));

        return self::created($message->load(['author', 'reactions', 'parent'])->toResource(), 'Message sent');
    }

    /**
     * The author changes the text of their message.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'New text of the message in Markdown', required: true, type: 'string')]
    final public function update(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        $message = app(UpdatesChatMessages::class)->update($request->user() ?? throw new AuthenticationException, $message, $request->only(['body']));

        return self::success('Message updated', null, $message->load(['author', 'reactions', 'parent'])->toResource());
    }

    /**
     * The author or a member who moderates the space deletes a message. It stays as a marker.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        app(DeletesChatMessages::class)->delete($request->user() ?? throw new AuthenticationException, $message);

        return self::success('Message deleted');
    }

    /**
     * Remember the last first-level message that the member read in the space (unread markers).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('message_cuid', description: 'The last first-level message that the member saw', required: true, type: 'string')]
    final public function read(Request $request, Community $community, Space $space): JsonResponse
    {
        app(MarksChatAsRead::class)->markAsRead($request->user() ?? throw new AuthenticationException, $space, $request->only(['message_cuid']));

        return self::success('Read');
    }

    private static function assertChatSpace(Space $space): void
    {
        if ($space->type !== KnowiiSpaceType::Chat) {
            throw new BusinessException('Messages only exist in chat spaces');
        }
    }
}
