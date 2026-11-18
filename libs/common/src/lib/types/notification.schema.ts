import { z } from 'zod';
import { formatEventStart } from '../utils/time-zone.fn';
import { followReasonSchema } from './notification-follow.schema';
import { authorSchema } from './post.schema';

/**
 * The kinds of notifications (#1474, #1497, ADR 0042): level 1 ("Needs you") and level 2 ("Following").
 * WARNING: aligned with KnowiiNotificationKind.php
 */
export const notificationKindSchema = z.enum([
  'post_comment',
  'comment_reply',
  'mention',
  'thread_comment',
  'thread_reply',
  'space_post',
  'space_chat',
  'lesson_new',
  // A new event in an events space (#1513)
  'event_new',
]);
export type NotificationKind = z.infer<typeof notificationKindSchema>;

/**
 * The code of "Why did I get this?". WARNING: aligned with KnowiiNotificationReason.php
 */
export const notificationReasonSchema = z.enum([
  'wrote_post',
  'wrote_comment',
  'mentioned',
  'follows_thread',
  'space_everything',
  'started_course',
]);
export type NotificationReason = z.infer<typeof notificationReasonSchema>;

const namedSchema = z.object({ cuid: z.string(), slug: z.string(), name: z.string() });

/**
 * What the client needs to open the exact item of a notification or of a line of the "Catch up" card.
 * WARNING: aligned with NotificationResource::targetOf
 */
export const notificationTargetSchema = z.object({
  post_slug: z.string().nullable(),
  // The thread to follow or to mute (a post). A chat thread is `thread_cuid`, or the message itself
  post_cuid: z.string().nullable(),
  comment_cuid: z.string().nullable(),
  chat_message_cuid: z.string().nullable(),
  thread_cuid: z.string().nullable(),
  resource_cuid: z.string().nullable(),
  resource_slug: z.string().nullable(),
  // A new lesson of a course
  page_slug: z.string().nullable(),
  // A new event (#1513): the line opens the space of the event in this slice
  event_cuid: z.string().nullable(),
  event_slug: z.string().nullable(),
});
export type NotificationTarget = z.infer<typeof notificationTargetSchema>;

/**
 * A notification of the current member. WARNING: aligned with NotificationResource.php. The row holds no text: the title
 * and the excerpt come from the live content.
 */
export const notificationSchema = z.object({
  cuid: z.string(),
  kind: notificationKindSchema,
  // 1 "Needs you", 2 "Following"
  level: z.number().int(),
  // The code of "Why did I get this?"
  reason: notificationReasonSchema,
  // For a thread that the member follows: why (wrote, replied, manual)
  follow_reason: followReasonSchema.nullable().optional(),
  community: namedSchema,
  space: namedSchema.nullable(),
  // The last author, null when the account was deleted ("Deleted member")
  actor: authorSchema.nullable(),
  // Up to 3 authors of the bundle, newest first
  actors: z.array(authorSchema),
  // The authors of the bundle that are not named
  others_count: z.coerce.number().int(),
  // The live posts, comments, replies or lessons that a bundled row holds
  items_count: z.coerce.number().int(),
  title: z.string().nullable(),
  excerpt: z.string(),
  // What the client needs to open the exact item
  target: notificationTargetSchema,
  // The start of the event of an `event_new` row (#1513); null for the other kinds
  event_starts_at: z.coerce.date().nullable(),
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
 * The kinds of level 2, "Following": never counted by the bell, never announced (design 2.1, 9.3)
 */
export const FOLLOWING_KINDS: readonly NotificationKind[] = [
  'thread_comment',
  'thread_reply',
  'space_post',
  'space_chat',
  'lesson_new',
  'event_new',
];

/**
 * One sentence from the kind, the authors and the live counts (#1484, #1497, design 2.5):
 *
 * 1. "Anna replied to your post", "Marc mentioned you in Open discussion"
 * 2. "3 new replies in <title>", "Ben posted in <space>: <title>", "4 new posts in <space>", "New messages in <space>",
 *    "Lesson <title> of <course> is out"
 * 3. "Tue 18 Nov, 17:00 Central European Standard Time: <title>" for a new event (#1513), in the zone of the account (else
 *    of the browser)
 */
export function notificationSentence(notification: Notification, zone?: string | null): string {
  const who = notificationActorNames(notification);
  const space = notification.space?.name ?? notification.community.name;
  const count = Math.max(1, notification.items_count);
  const title = notification.title;
  switch (notification.kind) {
    case 'post_comment':
      return `${who} replied to your post`;
    case 'comment_reply':
      return `${who} replied to your comment`;
    case 'mention':
      return `${who} mentioned you in ${space}`;
    case 'thread_comment':
      return count > 1 ? `${count} new replies in ${title ?? 'a post you follow'}` : `${who} replied in ${title ?? 'a post you follow'}`;
    case 'thread_reply':
      return count > 1 ? `${count} new replies in a thread of ${space}` : `${who} replied in a thread of ${space}`;
    case 'space_post':
      if (count > 1) {
        return `${count} new posts in ${space}`;
      }
      return title ? `${who} posted in ${space}: ${title}` : `${who} posted in ${space}`;
    case 'space_chat':
      return `New messages in ${space}`;
    case 'lesson_new':
      if (count > 1) {
        return `${count} new lessons in ${space}`;
      }
      return title ? `Lesson ${title} of ${space} is out` : `A new lesson of ${space} is out`;
    case 'event_new':
      if (notification.event_starts_at && title) {
        return `${formatEventStart(notification.event_starts_at, zone)}: ${title}`;
      }
      return `New event in ${space}`;
  }
}

/**
 * Whether the sentence of the line already holds the title: the line shows no second title under it
 */
export function notificationSentenceHasTitle(notification: Notification): boolean {
  if (notification.title === null) {
    return false;
  }
  const single = notification.items_count <= 1;
  return (
    notification.kind === 'thread_comment' ||
    (notification.kind === 'event_new' && notification.event_starts_at !== null) ||
    ((notification.kind === 'space_post' || notification.kind === 'lesson_new') && single)
  );
}

/**
 * Whether the line shows the title and the excerpt of an item under its sentence (#1509). A line that bundles several new
 * posts or lessons of a space ("3 new posts in <space>") shows none: they belong to one item only.
 */
export function notificationShowsItem(notification: Pick<Notification, 'kind' | 'items_count'>): boolean {
  return !((notification.kind === 'space_post' || notification.kind === 'lesson_new') && notification.items_count > 1);
}

/**
 * The sentence of "Why did I get this?", from the code of the reason (design 8.1)
 */
export function notificationReasonSentence(notification: Pick<Notification, 'reason' | 'follow_reason' | 'space'>): string {
  const space = notification.space?.name;
  switch (notification.reason) {
    case 'wrote_post':
      return 'You wrote this post, and someone replied to it.';
    case 'wrote_comment':
      return 'You wrote this comment, and someone replied to it.';
    case 'mentioned':
      return 'Someone mentioned you.';
    case 'follows_thread':
      if (notification.follow_reason === 'wrote') {
        return 'You follow this thread because you wrote it.';
      }
      return notification.follow_reason === 'replied'
        ? 'You follow this thread because you replied in it.'
        : 'You follow this thread because you chose to follow it.';
    case 'space_everything':
      return `Your level for ${space ?? 'this space'} is Everything, so you get every new item.`;
    case 'started_course':
      return `You started ${space ?? 'this course'}, so you are told about its new lessons.`;
  }
}

/**
 * The name of the link to the setting behind a notification: never a bare "settings"
 */
export function notificationSettingsLinkName(notification: Pick<Notification, 'space' | 'community'>): string {
  return `Notification settings for ${notification.space?.name ?? notification.community.name}`;
}

/**
 * The kinds of lines of the "Catch up" card (#1476, ADR 0048). WARNING: aligned with BuildsCatchUp.php
 */
export const catchUpLineKindSchema = z.enum(['needs_you', 'announcement', 'thread']);
export type CatchUpLineKind = z.infer<typeof catchUpLineKindSchema>;

/**
 * One line of the "Catch up" card: no count, no stored text. WARNING: aligned with BuildsCatchUp.php
 */
export const catchUpLineSchema = z.object({
  kind: catchUpLineKindSchema,
  title: z.string().nullable(),
  excerpt: z.string(),
  space: namedSchema.nullable(),
  author: authorSchema.nullable(),
  date: z.coerce.date(),
  target: notificationTargetSchema,
});
export type CatchUpLine = z.infer<typeof catchUpLineSchema>;

/**
 * The answer of the catch-up route: at most 5 lines, and whether more exists
 */
export const catchUpSchema = z.object({ lines: z.array(catchUpLineSchema), has_more: z.boolean() });
export type CatchUp = z.infer<typeof catchUpSchema>;

/**
 * The shared Inertia prop `catchUp`: the date of the last visit when it was 7 days old or more, else null
 */
export const catchUpPropSchema = z.object({ since: z.string() }).nullable();
export type CatchUpProp = z.infer<typeof catchUpPropSchema>;

/**
 * The first line of a line of the card: the title, else the excerpt
 */
export function catchUpLineHeadline(line: Pick<CatchUpLine, 'title' | 'excerpt'>): string {
  return line.title !== null && line.title !== '' ? line.title : line.excerpt;
}

/**
 * The text under the headline: the excerpt, when the headline is the title and there is an excerpt. Else null (the card then
 * shows the space and the date).
 */
export function catchUpLineDetail(line: Pick<CatchUpLine, 'title' | 'excerpt'>): string | null {
  return line.title !== null && line.title !== '' && line.excerpt !== '' ? line.excerpt : null;
}

/**
 * The number of the tab title: the bell number plus the number of "Messages" (design 5.4). "9+" above 9, nothing at zero.
 */
export function tabTitleBadge(count: number, messages: number): string | null {
  const total = Math.max(0, count) + Math.max(0, messages);
  if (total <= 0) {
    return null;
  }
  return total > 9 ? '9+' : String(total);
}

/**
 * The title of the browser tab: "(3) Title". No number at zero.
 */
export function tabTitle(title: string, count: number, messages: number): string {
  const badge = tabTitleBadge(count, messages);
  return badge === null ? title : `(${badge}) ${title}`;
}
