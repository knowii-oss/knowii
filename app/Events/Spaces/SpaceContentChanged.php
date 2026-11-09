<?php

namespace App\Events\Spaces;

use App\Constants;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * A post, a comment or the reactions of a space changed (#1180). The event goes to the channel of the space only,
 * which checks SpacePolicy::view (members-only and access group spaces never leak).
 *
 * The payload holds identifiers only: the open pages load the item again through the API, which checks the access and
 * computes the fields of the member (their own reactions).
 * WARNING: the names are aligned with use-socket.ts (libs/common)
 */
class SpaceContentChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const string POST_CREATED = 'space.post.created';

    public const string POST_UPDATED = 'space.post.updated';

    public const string POST_DELETED = 'space.post.deleted';

    public const string COMMENT_CREATED = 'space.comment.created';

    public const string COMMENT_UPDATED = 'space.comment.updated';

    public const string COMMENT_DELETED = 'space.comment.deleted';

    public const string REACTIONS_CHANGED = 'space.reactions.changed';

    // Pages of a page space (#1179)
    public const string PAGE_CREATED = 'space.page.created';

    public const string PAGE_UPDATED = 'space.page.updated';

    public const string PAGE_DELETED = 'space.page.deleted';

    public const string PAGES_REORDERED = 'space.pages.reordered';

    // Messages of a chat space (#1185)
    public const string MESSAGE_CREATED = 'space.message.created';

    public const string MESSAGE_UPDATED = 'space.message.updated';

    public const string MESSAGE_DELETED = 'space.message.deleted';

    /**
     * @param  array<string, string|null>  $payload
     */
    public function __construct(
        public readonly string $name,
        public readonly string $communityCuid,
        public readonly string $spaceCuid,
        public readonly array $payload,
    ) {}

    public static function forPost(string $name, Post $post, ?string $authorCuid = null): self
    {
        return self::in($post->space, $name, ['post_cuid' => $post->cuid, 'author_cuid' => $authorCuid]);
    }

    public static function forPage(string $name, Page $page, ?string $authorCuid = null): self
    {
        return self::in($page->space, $name, ['page_cuid' => $page->cuid, 'author_cuid' => $authorCuid]);
    }

    public static function forMessage(string $name, ChatMessage $message, ?string $authorCuid = null): self
    {
        return self::in($message->space, $name, [
            'message_cuid' => $message->cuid,
            'parent_cuid' => $message->parent_id === null ? null : $message->parent?->cuid,
            'author_cuid' => $authorCuid,
        ]);
    }

    public static function forPagesOrder(Space $space): self
    {
        return self::in($space, self::PAGES_REORDERED, []);
    }

    public static function forComment(string $name, Comment $comment, ?string $authorCuid = null): self
    {
        $subject = $comment->subject();

        return self::in($subject->space, $name, [
            ...self::subjectPayload($subject),
            'comment_cuid' => $comment->cuid,
            'author_cuid' => $authorCuid,
        ]);
    }

    /**
     * The reactions of a post or of a comment changed
     */
    public static function forReactions(Post|Comment|ChatMessage $reactable): self
    {
        if ($reactable instanceof ChatMessage) {
            return self::in($reactable->space, self::REACTIONS_CHANGED, [
                'message_cuid' => $reactable->cuid,
                'parent_cuid' => $reactable->parent_id === null ? null : $reactable->parent?->cuid,
            ]);
        }

        if ($reactable instanceof Post) {
            return self::in($reactable->space, self::REACTIONS_CHANGED, ['post_cuid' => $reactable->cuid]);
        }

        $subject = $reactable->subject();

        return self::in($subject->space, self::REACTIONS_CHANGED, [...self::subjectPayload($subject), 'comment_cuid' => $reactable->cuid]);
    }

    /**
     * @return array<string, string|null>
     */
    private static function subjectPayload(Post|CommunityResource $subject): array
    {
        return $subject instanceof Post ? ['post_cuid' => $subject->cuid] : ['resource_cuid' => $subject->cuid];
    }

    /**
     * @param  array<string, string|null>  $payload
     */
    private static function in(Space $space, string $name, array $payload): self
    {
        return new self($name, $space->community->cuid, $space->cuid, $payload);
    }

    /**
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return ['space_cuid' => $this->spaceCuid, ...$this->payload];
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(Str::of(Constants::WS_CHANNEL_COMMUNITY_SPACE)
                ->replace(Constants::WS_CHANNEL_COMMUNITY_SPACE_PARAM_COMMUNITY_CUID, $this->communityCuid)
                ->replace(Constants::WS_CHANNEL_COMMUNITY_SPACE_PARAM_SPACE_CUID, $this->spaceCuid)
                ->toString()),
        ];
    }
}
