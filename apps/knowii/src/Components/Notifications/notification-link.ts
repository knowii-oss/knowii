import type { Notification } from '@knowii/common';
import { EVENT_URL, POST_URL, RESOURCE_URL, SPACE_URL } from '@knowii/common';

type RouteFn = (name: string, params: Record<string, string>) => string;

/**
 * The anchor of a comment (#1205). WARNING: the same as `commentAnchor` in CommentSection (not imported: the layout would
 * load the whole comment section). A test keeps them equal.
 */
export const commentHash = (cuid: string) => `comment-${cuid}`;

/**
 * The anchor of a chat message in the page: `#message-{cuid}`, like `#comment-{cuid}` for the comments (#1205)
 */
export const messageAnchor = (cuid: string) => `message-${cuid}`;

/**
 * The query parameter of a chat space that opens the thread of a message: `?thread={cuid}`
 */
export const THREAD_QUERY_PARAM = 'thread';

/**
 * The address of the exact item that a notification is about (#1484):
 *
 * 1. a comment of a post: the post page with `#comment-{cuid}` (the form of the permalinks, #1205)
 * 2. a comment of a resource: the resource page with `#comment-{cuid}`
 * 3. a chat message: the chat space with `#message-{cuid}`, and `?thread={parent}` when the message is a reply
 * 4. a mention in a post: the post page
 * 5. a new event: the page of the event (#1514), or the page of its space when the target has no slug of the event
 *
 * Null when the notification has no space (it cannot be opened). A line of the "Catch up" card (#1476) has the same target.
 */
export function notificationHref(notification: Pick<Notification, 'community' | 'space' | 'target'>, route: RouteFn): string | null {
  const { community, space, target } = notification;
  if (!space) {
    return null;
  }
  const base = { communitySlug: community.slug, spaceSlug: space.slug };

  if (target.chat_message_cuid) {
    const thread = target.thread_cuid ? `?${THREAD_QUERY_PARAM}=${encodeURIComponent(target.thread_cuid)}` : '';
    return `${route(SPACE_URL, base)}${thread}#${messageAnchor(target.chat_message_cuid)}`;
  }
  if (target.resource_slug) {
    const anchor = target.comment_cuid ? `#${commentHash(target.comment_cuid)}` : '';
    return `${route(RESOURCE_URL, { ...base, resourceSlug: target.resource_slug })}${anchor}`;
  }
  if (target.event_slug) {
    return route(EVENT_URL, { ...base, eventSlug: target.event_slug });
  }
  if (target.event_cuid) {
    return route(SPACE_URL, base);
  }
  if (target.post_slug) {
    const anchor = target.comment_cuid ? `#${commentHash(target.comment_cuid)}` : '';
    return `${route(POST_URL, { ...base, postSlug: target.post_slug })}${anchor}`;
  }
  return null;
}
