import type { ApiResult, Notification } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { vi } from 'vitest';

export const person = (name: string) => ({ cuid: name.toLowerCase(), name, username: name.toLowerCase(), avatar_url: null });

/**
 * A notification of the server, with the defaults of a reply to a post by Ben
 */
export function makeNotification(cuid: string, changes: Partial<Notification> = {}): Notification {
  return {
    cuid,
    kind: 'post_comment',
    level: 1,
    reason: 'wrote_post',
    follow_reason: null,
    community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
    space: { cuid: 's1', slug: 'open', name: 'Open discussion' },
    actor: person('Ben'),
    actors: [person('Ben')],
    others_count: 0,
    items_count: 1,
    title: `Title of ${cuid}`,
    excerpt: `Excerpt of ${cuid}`,
    target: {
      post_slug: `post-${cuid}`,
      post_cuid: `post-cuid-${cuid}`,
      comment_cuid: `comment-${cuid}`,
      chat_message_cuid: null,
      thread_cuid: null,
      resource_cuid: null,
      resource_slug: null,
      page_slug: null,
    },
    seen_at: null,
    read_at: null,
    created_at: new Date('2026-11-15T10:00:00Z'),
    updated_at: new Date('2026-11-15T10:00:00Z'),
    ...changes,
  };
}

export function page(items: Notification[], nextOffset: number | null = null) {
  return {
    ok: true as const,
    message: '',
    data: {
      items,
      pagination: {
        limit: 20,
        offset: 0,
        previousOffset: null,
        nextOffset,
        currentPage: 1,
        pageCount: 1,
        totalCount: items.length,
      },
    },
  };
}

export const failure = (status = 500): ApiResult<never> => ({ ok: false, status, message: 'Failed', errors: {} });

/**
 * A "Following" line (level 2): a new comment in a thread that the member follows, by default
 */
export function makeFollowing(cuid: string, changes: Partial<Notification> = {}): Notification {
  return makeNotification(cuid, {
    kind: 'thread_comment',
    level: 2,
    reason: 'follows_thread',
    follow_reason: 'replied',
    items_count: 3,
    ...changes,
  });
}

/**
 * Spies on the notification API: the count, the list, seen, read and unread
 */
export function spyOnNotificationApi() {
  const client = knowiiApiClient.notifications;
  return {
    count: vi.spyOn(client, 'count').mockResolvedValue({ ok: true, message: '', data: { count: 0 } }),
    list: vi.spyOn(client, 'list').mockResolvedValue(page([])),
    following: vi.spyOn(client, 'listFollowing').mockResolvedValue(page([])),
    caughtUp: vi.spyOn(client, 'caughtUp').mockResolvedValue({ ok: true, message: '', data: { marked: 0 } }),
    seen: vi.spyOn(client, 'seen').mockResolvedValue({ ok: true, message: '', data: undefined }),
    read: vi
      .spyOn(client, 'read')
      .mockImplementation((cuid) =>
        Promise.resolve({ ok: true, message: '', data: makeNotification(cuid, { read_at: new Date(), seen_at: new Date() }) }),
      ),
    unread: vi
      .spyOn(client, 'unread')
      .mockImplementation((cuid) => Promise.resolve({ ok: true, message: '', data: makeNotification(cuid, { seen_at: new Date() }) })),
  };
}

/**
 * Spies on the follow actions of a thread and on the levels of the spaces (#1497)
 */
export function spyOnFollowApi() {
  const threads = knowiiApiClient.threads;
  const levels = knowiiApiClient.notificationLevels;
  const follow = (state: 'following' | 'muted' | 'stopped' | null, reason: 'wrote' | 'replied' | 'manual' | null = null) => ({
    ok: true as const,
    message: '',
    data: { state, reason },
  });
  const level = (value: 'all' | 'mine' | 'none', chosen = true) => ({
    ok: true as const,
    message: '',
    data: { space_cuid: 's1', level: value, chosen, default: 'mine' as const },
  });
  return {
    follow,
    level,
    getPost: vi.spyOn(threads, 'getPost').mockResolvedValue(follow(null)),
    followPost: vi.spyOn(threads, 'followPost').mockResolvedValue(follow('following', 'manual')),
    stopPost: vi.spyOn(threads, 'stopPost').mockResolvedValue(follow('stopped', 'manual')),
    mutePost: vi.spyOn(threads, 'mutePost').mockResolvedValue(follow('muted', 'manual')),
    getMessage: vi.spyOn(threads, 'getMessage').mockResolvedValue(follow(null)),
    followMessage: vi.spyOn(threads, 'followMessage').mockResolvedValue(follow('following', 'manual')),
    stopMessage: vi.spyOn(threads, 'stopMessage').mockResolvedValue(follow('stopped', 'manual')),
    muteMessage: vi.spyOn(threads, 'muteMessage').mockResolvedValue(follow('muted', 'manual')),
    getLevel: vi.spyOn(levels, 'get').mockResolvedValue(level('mine', false)),
    setLevel: vi.spyOn(levels, 'set').mockImplementation((_community, _space, value) => Promise.resolve(level(value))),
    resetLevel: vi.spyOn(levels, 'reset').mockResolvedValue(level('mine', false)),
    forCommunity: vi.spyOn(levels, 'forCommunity').mockResolvedValue({ ok: true, message: '', data: [] }),
  };
}
