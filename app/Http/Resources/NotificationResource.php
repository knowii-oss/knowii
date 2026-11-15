<?php

namespace App\Http\Resources;

use App\Constants;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Post;
use App\Support\Excerpt;
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
        $page = $notification->page;
        $community = $notification->community;
        $space = $notification->space;

        return [
            'cuid' => $notification->cuid,
            'kind' => $notification->kind,
            'level' => $notification->kind->level(),
            // The code that the client turns into the sentence "Why did I get this?" (#1475)
            'reason' => $notification->kind->reason(),
            // For a thread that the member follows: why they follow it (wrote, replied, manual)
            'follow_reason' => $notification->getAttributes()['follow_reason'] ?? null,
            'community' => ['cuid' => $community->cuid, 'slug' => $community->slug, 'name' => $community->name],
            'space' => $space === null ? null : ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name],
            // The last author, or null when the account was deleted ("Deleted member")
            'actor' => $notification->actor?->toResource(AuthorResource::class),
            // Up to 3 authors of the bundle (newest first, blocked and deleted authors left out), and the others
            'actors' => $this->whenLoaded('bundledActors', fn () => $notification->bundledActors->toResourceCollection(AuthorResource::class), []),
            'others_count' => $notification->getAttributes()['others_count'] ?? 0,
            // The live posts, comments, replies or lessons that a bundled row holds (set by the list)
            'items_count' => $notification->getAttributes()['items_count'] ?? 0,
            // The marker of a deleted post or comment shows nothing of the old content (#1344)
            'title' => $page->title ?? ($post === null || $post->isDeleted() ? null : $post->title),
            'excerpt' => self::excerpt($page ?? ($post !== null && $comment === null ? $post : ($comment ?? $message))),
            // What the client needs to open the exact item
            'target' => [
                'post_slug' => $post?->slug,
                // The thread to follow or to mute: a post (#1497). A chat thread is `thread_cuid`, or the message itself
                'post_cuid' => $post?->cuid,
                'comment_cuid' => $comment?->cuid,
                'chat_message_cuid' => $message?->cuid,
                'thread_cuid' => $message?->parent?->cuid,
                'resource_cuid' => $comment?->communityResource?->cuid,
                // The page of a resource is addressed by its slug
                'resource_slug' => $comment?->communityResource?->slug,
                // A new lesson of a course (#1475): the lesson is addressed by its slug
                'page_slug' => $page?->slug,
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
    private static function excerpt(Post|Comment|ChatMessage|Page|null $item): string
    {
        if ($item === null) {
            return '';
        }

        return Excerpt::of($item->body, Constants::NOTIFICATIONS_EXCERPT_LENGTH);
    }
}
