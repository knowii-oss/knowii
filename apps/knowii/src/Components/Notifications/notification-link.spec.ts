import { describe, expect, it } from 'vitest';
import type { NotificationTarget } from '@knowii/common';
import { commentAnchor } from '@/Components/Comments/CommentSection';
import { makeNotification } from '@/test-utils/notifications';
import { commentHash, messageAnchor, notificationHref } from './notification-link';
import { threadOf } from './notification-thread';

const route = (name: string, params: Record<string, string>) => `/${name}/${Object.values(params).join('/')}`;

describe('notificationHref (#1484)', () => {
  it('uses the anchor of the comment permalinks (#1205)', () => {
    expect(commentHash('abc')).toBe(commentAnchor('abc'));
  });

  it('opens the post page at the comment', () => {
    expect(notificationHref(makeNotification('a'), route)).toBe('/posts.show/knowii/open/post-a#comment-comment-a');
  });

  it('opens the post page for a mention in the post itself', () => {
    const mention = makeNotification('a', {
      kind: 'mention',
      target: {
        post_slug: 'my-post',
        post_cuid: 'p1',
        comment_cuid: null,
        chat_message_cuid: null,
        thread_cuid: null,
        resource_cuid: null,
        resource_slug: null,
        page_slug: null,
        event_cuid: null,
        event_slug: null,
      },
    });

    expect(notificationHref(mention, route)).toBe('/posts.show/knowii/open/my-post');
  });

  it('opens the chat space at the message, and at its thread for a reply', () => {
    const target: NotificationTarget = {
      post_slug: null,
      post_cuid: null,
      comment_cuid: null,
      chat_message_cuid: 'm1',
      thread_cuid: null,
      resource_cuid: null,
      resource_slug: null,
      page_slug: null,
      event_cuid: null,
      event_slug: null,
    };

    expect(notificationHref(makeNotification('a', { kind: 'mention', target }), route)).toBe(
      `/spaces.show/knowii/open#${messageAnchor('m1')}`,
    );
    expect(notificationHref(makeNotification('a', { kind: 'mention', target: { ...target, thread_cuid: 'root' } }), route)).toBe(
      '/spaces.show/knowii/open?thread=root#message-m1',
    );
  });

  it('opens the resource page at the comment', () => {
    const target: NotificationTarget = {
      post_slug: null,
      post_cuid: null,
      comment_cuid: 'k1',
      chat_message_cuid: null,
      thread_cuid: null,
      resource_cuid: 'r1',
      resource_slug: 'a-resource',
      page_slug: null,
      event_cuid: null,
      event_slug: null,
    };

    expect(notificationHref(makeNotification('a', { target }), route)).toBe('/resources.show/knowii/open/a-resource#comment-k1');
  });

  it('opens the page of a new event, and the page of its space when the target has no slug of the event (#1514)', () => {
    const target: NotificationTarget = {
      post_slug: null,
      post_cuid: null,
      comment_cuid: null,
      chat_message_cuid: null,
      thread_cuid: null,
      resource_cuid: null,
      resource_slug: null,
      page_slug: null,
      event_cuid: 'e1',
      event_slug: 'weekly-review',
    };

    expect(notificationHref(makeNotification('a', { kind: 'event_new', level: 2, target }), route)).toBe(
      '/events.show/knowii/open/weekly-review',
    );
    // A row of an older server names the event by its cuid only
    expect(notificationHref(makeNotification('a', { kind: 'event_new', level: 2, target: { ...target, event_slug: null } }), route)).toBe(
      '/spaces.show/knowii/open',
    );
    expect(threadOf(makeNotification('a', { kind: 'event_new', level: 2, target }))).toBeNull();
  });

  it('has no address without a space, or without a target', () => {
    expect(notificationHref(makeNotification('a', { space: null }), route)).toBeNull();
    const empty: NotificationTarget = {
      post_slug: null,
      post_cuid: null,
      comment_cuid: null,
      chat_message_cuid: null,
      thread_cuid: null,
      resource_cuid: null,
      resource_slug: null,
      page_slug: null,
      event_cuid: null,
      event_slug: null,
    };
    expect(notificationHref(makeNotification('a', { target: empty }), route)).toBeNull();
  });
});
