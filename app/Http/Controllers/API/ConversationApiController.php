<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Contracts\Chat\DeletesChatMessages;
use App\Contracts\Chat\UpdatesChatMessages;
use App\Contracts\Conversations\MarksConversationsAsRead;
use App\Contracts\Conversations\SendsDirectMessages;
use App\Contracts\Conversations\StartsConversations;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Conversations;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Direct messages (#1186, ADR 0028): the conversations of the current member in a community and their messages.
 * Participants only. Cursor pagination for the messages, like the chat (ADR 0011).
 */
class ConversationApiController extends Controller
{
    use ApiResponses;

    /**
     * The conversations of the current member in the community, newest first, with the newest message and the unread
     * count. `metadata.unread_total` counts the unread messages of every conversation.
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    #[QueryParameter('limit', description: 'Conversations per page (1 to 50, default 20)', type: 'int')]
    #[QueryParameter('offset', description: 'Conversations to skip', type: 'int')]
    final public function index(Request $request, Community $community): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community have direct messages');
        }

        $page = Pagination::fromRequest($request)->apply(Conversations::of($user, $community));

        return self::success('Conversations', [
            ...$page['metadata'],
            'unread_total' => Conversations::unreadTotal($user, $community),
        ], $page['items']->toResourceCollection());
    }

    /**
     * Write to 1 to 9 other members with a first message. Writing again to the same single member continues your
     * one-to-one conversation with them. Returns the conversation.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('participant_cuids', description: 'The cuids of 1 to 9 other members of the community', required: true, type: 'array<string>')]
    #[BodyParameter('body', description: 'The first message, in Markdown', required: true, type: 'string')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $conversation = app(StartsConversations::class)->start($user, $community, $request->only(['participant_cuids', 'body']));

        return self::created(self::load($conversation, $user), 'Message sent');
    }

    /**
     * One conversation with its participants.
     *
     * @throws AuthenticationException
     */
    final public function show(Request $request, Community $community, Conversation $conversation): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        Gate::authorize('view', $conversation);

        return self::success('Conversation', null, self::load($conversation, $user));
    }

    /**
     * The messages of a conversation, oldest first in the page. Without `before`, the newest page; with `before` (a
     * message cuid), the older messages before it. `metadata.has_more` says whether older ones exist.
     */
    #[QueryParameter('before', description: 'The cuid of a message: return the messages before it', type: 'string')]
    final public function messages(Request $request, Community $community, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $query = $conversation->messages()->with(['author', 'reactions']);
        $before = $request->query('before');
        if (is_string($before) && $before !== '') {
            $cursor = $conversation->messages()->where('cuid', $before)->value('id');
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
    final public function message(Community $community, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        return self::success('Message', null, $message->load(['author', 'reactions'])->toResource());
    }

    /**
     * Write a message in the conversation.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Text of the message in Markdown', required: true, type: 'string')]
    final public function send(Request $request, Community $community, Conversation $conversation): JsonResponse
    {
        $message = app(SendsDirectMessages::class)->send($request->user() ?? throw new AuthenticationException, $conversation, $request->only(['body']));

        return self::created($message->load(['author', 'reactions'])->toResource(), 'Message sent');
    }

    /**
     * The author changes the text of their message.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'New text of the message in Markdown', required: true, type: 'string')]
    final public function updateMessage(Request $request, Community $community, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        $message = app(UpdatesChatMessages::class)->update($request->user() ?? throw new AuthenticationException, $message, $request->only(['body']));

        return self::success('Message updated', null, $message->load(['author', 'reactions'])->toResource());
    }

    /**
     * The author deletes their message. It stays as a marker.
     *
     * @throws AuthenticationException
     */
    final public function destroyMessage(Request $request, Community $community, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        app(DeletesChatMessages::class)->delete($request->user() ?? throw new AuthenticationException, $message);

        return self::success('Message deleted');
    }

    /**
     * Remember the last message that the member read in the conversation (unread counts).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('message_cuid', description: 'The last message that the member saw', required: true, type: 'string')]
    final public function read(Request $request, Community $community, Conversation $conversation): JsonResponse
    {
        app(MarksConversationsAsRead::class)->markAsRead($request->user() ?? throw new AuthenticationException, $conversation, $request->only(['message_cuid']));

        return self::success('Read');
    }

    /**
     * The conversation as the lists show it: participants, newest message, unread count of the member
     */
    private static function load(Conversation $conversation, User $user): ConversationResource
    {
        /** @var Conversation $loaded */
        $loaded = Conversations::of($user, $conversation->community)->where('conversations.id', $conversation->id)->firstOrFail();

        return $loaded->toResource();
    }
}
