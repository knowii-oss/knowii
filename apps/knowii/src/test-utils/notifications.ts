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
    community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
    space: { cuid: 's1', slug: 'open', name: 'Open discussion' },
    actor: person('Ben'),
    actors: [person('Ben')],
    others_count: 0,
    title: `Title of ${cuid}`,
    excerpt: `Excerpt of ${cuid}`,
    target: {
      post_slug: `post-${cuid}`,
      comment_cuid: `comment-${cuid}`,
      chat_message_cuid: null,
      thread_cuid: null,
      resource_cuid: null,
      resource_slug: null,
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
 * Spies on the notification API: the count, the list, seen, read and unread
 */
export function spyOnNotificationApi() {
  const client = knowiiApiClient.notifications;
  return {
    count: vi.spyOn(client, 'count').mockResolvedValue({ ok: true, message: '', data: { count: 0 } }),
    list: vi.spyOn(client, 'list').mockResolvedValue(page([])),
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
