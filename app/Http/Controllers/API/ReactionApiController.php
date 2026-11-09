<?php

namespace App\Http\Controllers\API;

use App\Contracts\Reactions\TogglesReactions;
use App\Enums\KnowiiReactionKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\Comment;
use App\Models\Community;
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
 * Reactions to posts and comments (#1176): toggle a reaction, list who reacted.
 */
class ReactionApiController extends Controller
{
    use ApiResponses;

    private const string KIND_DESCRIPTION = 'like, love, insightful, funny or celebrate';

    /**
     * Add or remove the reaction of the member to a post. Returns the new summary.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('kind', description: self::KIND_DESCRIPTION, required: true, type: 'string', example: 'like')]
    final public function togglePost(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        return $this->toggle($request, $post);
    }

    /**
     * Add or remove the reaction of the member to a comment. Returns the new summary.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('kind', description: self::KIND_DESCRIPTION, required: true, type: 'string', example: 'like')]
    final public function toggleComment(Request $request, Community $community, Space $space, Post $post, Comment $comment): JsonResponse
    {
        return $this->toggle($request, $comment);
    }

    /**
     * Who reacted to a post, per kind.
     */
    final public function listPost(Community $community, Space $space, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        return self::success('Reactions', null, self::whoReacted($post));
    }

    /**
     * Who reacted to a comment, per kind.
     */
    final public function listComment(Community $community, Space $space, Post $post, Comment $comment): JsonResponse
    {
        Gate::authorize('view', $comment);

        return self::success('Reactions', null, self::whoReacted($comment));
    }

    /**
     * @throws AuthenticationException
     */
    private function toggle(Request $request, Post|Comment $reactable): JsonResponse
    {
        $summary = app(TogglesReactions::class)->toggle($request->user() ?? throw new AuthenticationException, $reactable, $request->only(['kind']));

        return self::success('Reactions updated', null, $summary);
    }

    /**
     * @return list<array{kind: string, members: array<int, array<string, mixed>>}>
     */
    private static function whoReacted(Post|Comment $reactable): array
    {
        $reactions = $reactable->reactions()->with('user')->orderBy('created_at')->get();
        $result = [];

        foreach (KnowiiReactionKind::cases() as $kind) {
            $members = $reactions
                ->filter(fn (Reaction $reaction) => $reaction->kind === $kind)
                ->map(fn (Reaction $reaction) => (new AuthorResource($reaction->user))->toArray(request()))
                ->values()
                ->all();

            if ($members !== []) {
                $result[] = ['kind' => $kind->value, 'members' => $members];
            }
        }

        return $result;
    }
}
