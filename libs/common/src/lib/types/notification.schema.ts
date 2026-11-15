import { z } from 'zod';
import { authorSchema } from './post.schema';

/**
 * The kinds of notifications of level 1, "Needs you" (#1474, ADR 0042). WARNING: aligned with KnowiiNotificationKind.php
 */
export const notificationKindSchema = z.enum(['post_comment', 'comment_reply', 'mention']);
export type NotificationKind = z.infer<typeof notificationKindSchema>;

const namedSchema = z.object({ cuid: z.string(), slug: z.string(), name: z.string() });

/**
 * A notification of the current member. WARNING: aligned with NotificationResource.php. The row holds no text: the title
 * and the excerpt come from the live content.
 */
export const notificationSchema = z.object({
  cuid: z.string(),
  kind: notificationKindSchema,
  level: z.number().int(),
  community: namedSchema,
  space: namedSchema.nullable(),
  // The last author, null when the account was deleted ("Deleted member")
  actor: authorSchema.nullable(),
  // Up to 3 authors of the bundle, newest first
  actors: z.array(authorSchema),
  // The authors of the bundle that are not named
  others_count: z.coerce.number().int(),
  title: z.string().nullable(),
  excerpt: z.string(),
  // What the client needs to open the exact item
  target: z.object({
    post_slug: z.string().nullable(),
    comment_cuid: z.string().nullable(),
    chat_message_cuid: z.string().nullable(),
    thread_cuid: z.string().nullable(),
    resource_cuid: z.string().nullable(),
    resource_slug: z.string().nullable(),
  }),
  seen_at: z.coerce.date().nullable(),
  read_at: z.coerce.date().nullable(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});
export type Notification = z.infer<typeof notificationSchema>;

/**
 * The payload of the event `notifications.changed` of the channel `user.{cuid}`: identifiers only.
 * WARNING: aligned with NotificationsChanged.php
 */
export interface NotificationsChangedPayload {
  // Null when more than one row changed
  notification_cuid: string | null;
  community_cuid: string;
}

/**
 * The number that the bell shows: 1 to 9, then "9+", and nothing at zero
 */
export function notificationBadge(count: number): string | null {
  if (count <= 0) {
    return null;
  }
  return count > 9 ? '9+' : String(count);
}

/**
 * The accessible name of the bell: the badge is hidden from assistive technology, so the name holds the number
 */
export function notificationBellName(count: number): string {
  if (count <= 0) {
    return 'Notifications';
  }
  return count > 9 ? 'Notifications, 9 or more new' : `Notifications, ${count} new`;
}

/**
 * The names of the authors of a notification, as words: "Anna", "Anna and Ben", "Anna, Ben and Carl", "Anna, Ben, Carl and
 * 2 others". A deleted author shows as "Deleted member".
 */
export function notificationActorNames(notification: Pick<Notification, 'actor' | 'actors' | 'others_count'>): string {
  const authors = notification.actors.length > 0 ? notification.actors : notification.actor ? [notification.actor] : [];
  const names = authors.map((author) => author.name);
  if (names.length === 0) {
    return 'Deleted member';
  }
  const others = notification.others_count;
  if (others > 0) {
    return `${names.join(', ')} and ${others === 1 ? '1 other' : `${others} others`}`;
  }
  if (names.length === 1) {
    return names[0] ?? 'Deleted member';
  }
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1] ?? ''}`;
}

/**
 * One sentence from the kind and the authors: "Anna replied to your post", "Marc mentioned you in Open discussion"
 */
export function notificationSentence(notification: Notification): string {
  const who = notificationActorNames(notification);
  switch (notification.kind) {
    case 'post_comment':
      return `${who} replied to your post`;
    case 'comment_reply':
      return `${who} replied to your comment`;
    case 'mention':
      return `${who} mentioned you in ${notification.space?.name ?? notification.community.name}`;
  }
}
