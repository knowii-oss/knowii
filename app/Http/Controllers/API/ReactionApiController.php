<?php

namespace App\Http\Controllers\API;

use App\Contracts\Reactions\TogglesReactions;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Reactions to posts, comments and messages (#1176, any emoji since #1204): toggle a reaction, list who reacted.
 */
class ReactionApiController extends Controller
{
    use ApiResponses;

    private const string EMOJI_DESCRIPTION = 'An emoji of the picker (Unicode, skin tone included), for example 👍. A member reacts once with each emoji; an item takes at most 20 different emoji (422 above)';

    /**
     * Add or remove the reaction of the member to a post. Returns the new summary.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('emoji', description: self::EMOJI_DESCRIPTION, required: true, type: 'string', example: '👍')]
    final public function togglePost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return $this->toggle($request, $post);
    }

    /**
     * Add or remove the reaction of the member to a comment. Returns the new summary.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('emoji', description: self::EMOJI_DESCRIPTION, required: true, type: 'string', example: '👍')]
    final public function toggleComment(Request $request, Community $community, Space $space, Post $post, Comment $comment): JsonResponse
    {
        return $this->toggle($request, $comment);
    }

    /**
     * Add or remove the reaction of the member to a comment on a resource (#1034). Returns the new summary.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('emoji', description: self::EMOJI_DESCRIPTION, required: true, type: 'string', example: '👍')]
    final public function toggleResourceComment(Request $request, Community $community, Space $space, CommunityResource $resource, Comment $comment): JsonResponse
    {
        return $this->toggle($request, $comment);
    }

    /**
     * Who reacted to a comment on a resource, per emoji (#1034).
     */
    final public function listResourceComment(Community $community, Space $space, CommunityResource $resource, Comment $comment): JsonResponse
    {
        Gate::authorize('view', $comment);

        return self::success('Reactions', null, self::whoReacted($comment));
    }

    /**
     * Who reacted to a post, per emoji.
     */
    final public function listPost(Community $community, Space $space, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        return self::success('Reactions', null, self::whoReacted($post));
    }

    /**
     * Who reacted to a comment, per emoji.
     */
    final public function listComment(Community $community, Space $space, Post $post, Comment $comment): JsonResponse
    {
        Gate::authorize('view', $comment);

        return self::success('Reactions', null, self::whoReacted($comment));
    }

    /**
     * Add or remove a reaction of the current member on a chat message (#1185).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('emoji', description: self::EMOJI_DESCRIPTION, required: true, type: 'string', example: '👍')]
    final public function toggleMessage(Request $request, Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        return $this->toggle($request, $message);
    }

    /**
     * Who reacted to a chat message, per emoji (#1185).
     */
    final public function listMessage(Community $community, Space $space, ChatMessage $message): JsonResponse
    {
        Gate::authorize('view', $space);

        return self::success('Reactions', null, self::whoReacted($message));
    }

    /**
     * Add or remove a reaction of the current member on a direct message (#1186).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('emoji', description: self::EMOJI_DESCRIPTION, required: true, type: 'string', example: '👍')]
    final public function toggleDirectMessage(Request $request, Community $community, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        return $this->toggle($request, $message);
    }

    /**
     * Who reacted to a direct message, per emoji (#1186).
     */
    final public function listDirectMessage(Community $community, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        return self::success('Reactions', null, self::whoReacted($message));
    }

    /**
     * @throws AuthenticationException
     */
    private function toggle(Request $request, Post|Comment|ChatMessage $reactable): JsonResponse
    {
        $summary = app(TogglesReactions::class)->toggle($request->user() ?? throw new AuthenticationException, $reactable, $request->only(['emoji']));

        return self::success('Reactions updated', null, $summary);
    }

    /**
     * The members per emoji, in the order of the first reaction with each emoji (#1204)
     *
     * @return list<array{emoji: string, members: array<int, array<string, mixed>>}>
     */
    private static function whoReacted(Post|Comment|ChatMessage $reactable): array
    {
        return array_values($reactable->reactions()->with('user')->orderBy('created_at')->orderBy('id')->get()
            ->groupBy('emoji')
            ->map(fn ($ofEmoji, $emoji) => [
                'emoji' => (string) $emoji,
                'members' => $ofEmoji->map(fn (Reaction $reaction) => $reaction->user->toResource(AuthorResource::class)->toArray(request()))->values()->all(),
            ])
            ->all());
    }
}
