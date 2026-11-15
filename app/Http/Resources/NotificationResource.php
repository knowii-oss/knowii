<?php

namespace App\Http\Resources;

use App\Constants;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use Illuminate\Http\Request;

/**
 * A notification of the current member (#1474, ADR 0042). The row holds no text: the title and the excerpt come from
 * the live content, and the bundled authors from NotificationBundles. The list loads the relations of all its rows at
 * once, so serializing a page runs no query per row.
 *
 * @mixin Notification
 */
class NotificationResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var Notification $notification */
        $notification = $this->resource;
        $comment = $notification->comment;
        $post = $notification->post ?? $comment?->post;
        $message = $notification->chatMessage;
        $community = $notification->community;
        $space = $notification->space;

        return [
            'cuid' => $notification->cuid,
            'kind' => $notification->kind,
            'level' => $notification->kind->level(),
            'community' => ['cuid' => $community->cuid, 'slug' => $community->slug, 'name' => $community->name],
            'space' => $space === null ? null : ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name],
            // The last author, or null when the account was deleted ("Deleted member")
            'actor' => $notification->actor?->toResource(AuthorResource::class),
            // Up to 3 authors of the bundle (newest first, blocked and deleted authors left out), and the others
            'actors' => $this->whenLoaded('bundledActors', fn () => $notification->bundledActors->toResourceCollection(AuthorResource::class), []),
            'others_count' => $notification->getAttributes()['others_count'] ?? 0,
            // The marker of a deleted post or comment shows nothing of the old content (#1344)
            'title' => $post === null || $post->isDeleted() ? null : $post->title,
            'excerpt' => self::excerpt($post !== null && $comment === null ? $post : ($comment ?? $message)),
            // What the client needs to open the exact item
            'target' => [
                'post_slug' => $post?->slug,
                'comment_cuid' => $comment?->cuid,
                'chat_message_cuid' => $message?->cuid,
                'thread_cuid' => $message?->parent?->cuid,
                'resource_cuid' => $comment?->communityResource?->cuid,
            ],
            'seen_at' => $notification->seen_at,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
        ];
    }

    /**
     * Plain text of the item, 300 characters at most: no HTML, no Markdown signs, one line
     */
    private static function excerpt(Post|Comment|ChatMessage|null $item): string
    {
        if ($item === null) {
            return '';
        }

        $text = html_entity_decode(strip_tags($item->body), ENT_QUOTES | ENT_HTML5);
        $text = (string) preg_replace(['/!?\[([^\]]*)\]\([^)]*\)/', '/[*_`>#~\[\]]+/', '/\s+/u'], ['$1', '', ' '], $text);
        $text = trim($text);

        return mb_strlen($text) > Constants::NOTIFICATIONS_EXCERPT_LENGTH
            ? rtrim(mb_substr($text, 0, Constants::NOTIFICATIONS_EXCERPT_LENGTH - 1)).'…'
            : $text;
    }
}
