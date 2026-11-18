<?php

namespace App\Http\Resources;

use App\Constants;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Event;
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
            'title' => self::titleOf($notification),
            'excerpt' => self::excerptOf($notification),
            // What the client needs to open the exact item
            'target' => self::targetOf($notification),
            // The start of the event of an `event_new` row (#1513): the client writes it in the zone of the reader. Null
            // for the other kinds
            'event_starts_at' => $notification->event?->starts_at,
            'seen_at' => $notification->seen_at,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
        ];
    }

    /**
     * The title, built from the live content. The marker of a deleted post or comment shows nothing of the old content (#1344)
     */
    public static function titleOf(Notification $notification): ?string
    {
        $post = $notification->post ?? $notification->comment?->post;

        if ($notification->event !== null) {
            return $notification->event->title;
        }

        return $notification->page->title ?? ($post === null || $post->isDeleted() ? null : $post->title);
    }

    /**
     * The plain text excerpt, built from the live content
     */
    public static function excerptOf(Notification $notification): string
    {
        $comment = $notification->comment;
        $post = $notification->post ?? $comment?->post;

        return self::excerpt($notification->event ?? $notification->page ?? ($post !== null && $comment === null ? $post : ($comment ?? $notification->chatMessage)));
    }

    /**
     * What the client needs to open the exact item (also used by the "Catch up" lines, #1476)
     *
     * @return array{post_slug: ?string, post_cuid: ?string, comment_cuid: ?string, chat_message_cuid: ?string, thread_cuid: ?string, resource_cuid: ?string, resource_slug: ?string, page_slug: ?string, event_cuid: ?string, event_slug: ?string}
     */
    public static function targetOf(Notification $notification): array
    {
        $comment = $notification->comment;
        $post = $notification->post ?? $comment?->post;
        $message = $notification->chatMessage;

        return [
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
            'page_slug' => $notification->page?->slug,
            // A new event (#1513): its space opens it in this slice; the event page comes with the screens
            'event_cuid' => $notification->event?->cuid,
            'event_slug' => $notification->event?->slug,
        ];
    }

    /**
     * Plain text of the item, 300 characters at most: no HTML, no Markdown signs, one line
     */
    private static function excerpt(Post|Comment|ChatMessage|Page|Event|null $item): string
    {
        if ($item === null) {
            return '';
        }

        return Excerpt::of($item->body, Constants::NOTIFICATIONS_EXCERPT_LENGTH);
    }
}
