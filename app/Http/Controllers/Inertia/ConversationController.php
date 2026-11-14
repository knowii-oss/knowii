<?php

namespace App\Http\Controllers\Inertia;

use App\Constants;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Conversations;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The direct messages of a member in a community (#1186, ADR 0028): the list of conversations and, when one is open,
 * its newest messages.
 */
class ConversationController extends Controller
{
    // The first page of the list; the page loads more through the API
    private const int CONVERSATIONS_ON_PAGE = 50;

    public function index(Request $request, string $communitySlug): Response
    {
        return $this->render($request, $communitySlug, null);
    }

    public function show(Request $request, string $communitySlug, string $conversationCuid): Response
    {
        return $this->render($request, $communitySlug, $conversationCuid);
    }

    private function render(Request $request, string $communitySlug, ?string $conversationCuid): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        /** @var User $user */
        $user = $request->user();
        if (! $user->belongsToCommunity($community)) {
            throw new AccessDeniedHttpException('Only the members of the community have direct messages');
        }

        $conversation = null;
        if ($conversationCuid !== null) {
            $conversation = $community->conversations()->where('cuid', $conversationCuid)->firstOrFail();
            Gate::authorize('view', $conversation);
        }

        JsonResource::withoutWrapping();

        // WARNING: aligned with the props of Pages/Messages/Index.tsx
        return Jetstream::inertia()->render($request, 'Messages/Index', [
            'community' => $community->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $user)->toResourceCollection(),
            'conversations' => Conversations::of($user, $community)->limit(self::CONVERSATIONS_ON_PAGE)->get()->toResourceCollection(),
            'conversation' => $conversation === null ? null : Conversations::of($user, $community)->where('conversations.id', $conversation->id)->first()?->toResource(),
            'messages' => $conversation === null ? null : self::messages($conversation, $user),
            'permissions' => [
                'canStart' => $community->allowsDirectMessages(),
                'canSend' => $conversation !== null && Gate::check('send', $conversation),
            ],
            // A one-to-one conversation: whether the member blocked the other one (Block or Unblock)
            'blockedByMe' => $conversation !== null && $conversation->isDirect()
                && $user->blockedUsers()->whereIn('users.id', $conversation->participants()->whereKeyNot($user->id)->select('users.id'))->exists(),
        ]);
    }

    /**
     * @return array{items: ResourceCollection, has_more: bool, first_unread_cuid: string|null}
     */
    private static function messages(Conversation $conversation, User $user): array
    {
        $items = $conversation->messages()->with(['author', 'reactions'])->orderByDesc('id')->limit(Constants::CHAT_MESSAGES_PER_PAGE + 1)->get();
        $page = $items->take(Constants::CHAT_MESSAGES_PER_PAGE)->reverse()->values();

        $value = $conversation->participants()->whereKey($user->id)->value('conversation_participants.last_read_message_id');
        $lastRead = is_numeric($value) ? (int) $value : 0;

        return [
            'items' => $page->toResourceCollection(),
            'has_more' => $items->count() > Constants::CHAT_MESSAGES_PER_PAGE,
            'first_unread_cuid' => $page->first(fn (ChatMessage $message) => $message->id > $lastRead && $message->author_id !== $user->id)?->cuid,
        ];
    }
}
