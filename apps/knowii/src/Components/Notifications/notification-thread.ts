import type { Notification } from '@knowii/common';

/**
 * The thread that a notification is about, when it has one (#1497): a post, or a first-level chat message (a reply in a chat
 * thread points at its first message). "Stop following" and "Mute this thread" work on it.
 */
export type ThreadRef = { kind: 'post'; postCuid: string } | { kind: 'message'; messageCuid: string };

/**
 * The lines of a space or of a course (new posts, new messages, new lessons, new events) belong to no thread: only the space
 * can be muted.
 */
export function threadOf(notification: Notification): ThreadRef | null {
  if (
    notification.kind === 'space_post' ||
    notification.kind === 'space_chat' ||
    notification.kind === 'lesson_new' ||
    notification.kind === 'event_new'
  ) {
    return null;
  }
  const { target } = notification;
  if (target.chat_message_cuid) {
    return { kind: 'message', messageCuid: target.thread_cuid ?? target.chat_message_cuid };
  }
  if (target.post_cuid && !target.resource_cuid) {
    return { kind: 'post', postCuid: target.post_cuid };
  }
  return null;
}

/**
 * "Stop following" is on the lines of a thread that the member follows
 */
export function canStopFollowing(notification: Notification): boolean {
  return notification.level === 2 && (notification.kind === 'thread_comment' || notification.kind === 'thread_reply');
}
