import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';

const notification = {
  cuid: 'n1',
  kind: 'post_comment',
  level: 1,
  reason: 'wrote_post',
  community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
  space: { cuid: 's1', slug: 'open', name: 'Open discussion' },
  actor: null,
  actors: [],
  others_count: 0,
  items_count: 1,
  title: null,
  excerpt: '',
  target: {
    post_slug: null,
    post_cuid: null,
    page_slug: null,
    comment_cuid: null,
    chat_message_cuid: null,
    thread_cuid: null,
    resource_cuid: null,
    resource_slug: null,
  },
  seen_at: null,
  read_at: null,
  created_at: '2026-11-15T10:00:00Z',
  updated_at: '2026-11-15T10:00:00Z',
};

const pagination = { limit: 20, offset: 0, previousOffset: null, nextOffset: 20, currentPage: 1, pageCount: 2, totalCount: 25 };

function answer(body: unknown, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve(body) });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('the notifications of the API client (#1484)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('lists a page, with the offset and the pagination', async () => {
    const fetchMock = answer({ data: [notification], metadata: { pagination } });

    const result = await knowiiApiClient.notifications.list({ offset: 20 });

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/notifications?limit=20&offset=20');
    expect(result.ok && result.data.items[0]?.cuid).toBe('n1');
    expect(result.ok && result.data.pagination.nextOffset).toBe(20);
  });

  it('asks for the older ones with older=true', async () => {
    const fetchMock = answer({ data: [], metadata: { pagination } });

    await knowiiApiClient.notifications.list({ older: true, limit: 5 });

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/notifications?limit=5&offset=0&older=true');
  });

  it('fails without throwing when the answer is not a list', async () => {
    answer({ data: 'nope' });

    const result = await knowiiApiClient.notifications.list();

    expect(result.ok).toBe(false);
  });

  it('loads the count', async () => {
    const fetchMock = answer({ data: { count: 4 } });

    const result = await knowiiApiClient.notifications.count();

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/notifications/count');
    expect(result.ok && result.data.count).toBe(4);
  });

  it('sends the cuids of the lines that were seen', async () => {
    const fetchMock = answer({ message: 'Notifications seen' });

    const result = await knowiiApiClient.notifications.seen(['a', 'b']);

    expect(result.ok).toBe(true);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/v1/notifications/seen');
    expect(init.method).toBe('POST');
    expect(init.body).toBe(JSON.stringify({ notification_cuids: ['a', 'b'] }));
  });

  it('marks a row read with PUT and open again with POST', async () => {
    const read = answer({ data: { ...notification, read_at: '2026-11-15T10:05:00Z' } });
    const done = await knowiiApiClient.notifications.read('n1');
    expect(read.mock.calls[0]).toEqual(['/api/v1/notifications/n1/read', expect.objectContaining({ method: 'PUT' })]);
    expect(done.ok && done.data.read_at).toBeInstanceOf(Date);

    const unread = answer({ data: notification });
    await knowiiApiClient.notifications.unread('n1');
    expect(unread.mock.calls[0]).toEqual(['/api/v1/notifications/n1/unread', expect.objectContaining({ method: 'POST' })]);
  });

  it('gives the business error of "Undo" as a 422', async () => {
    answer({ message: 'A newer notification about this already exists', errors: {} }, 422);

    const result = await knowiiApiClient.notifications.unread('n1');

    expect(result).toMatchObject({ ok: false, status: 422, message: 'A newer notification about this already exists' });
  });
});

describe('"Following", thread follows and levels of the API client (#1497)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('lists "Following" with level=2 and parses the level 2 kinds', async () => {
    const fetchMock = answer({
      data: [{ ...notification, kind: 'thread_comment', level: 2, reason: 'follows_thread', follow_reason: 'replied', items_count: 3 }],
      metadata: { pagination },
    });

    const result = await knowiiApiClient.notifications.listFollowing({ offset: 20 });

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/notifications?limit=20&offset=20&level=2');
    expect(result.ok && result.data.items[0]).toMatchObject({ kind: 'thread_comment', level: 2, items_count: 3, follow_reason: 'replied' });
  });

  it('says "I am caught up" for every community, or for one', async () => {
    const all = answer({ data: { marked: 4 } });
    const result = await knowiiApiClient.notifications.caughtUp();
    expect(all.mock.calls[0]).toEqual(['/api/v1/notifications/caught-up', expect.objectContaining({ method: 'POST', body: '{}' })]);
    expect(result.ok && result.data.marked).toBe(4);

    const one = answer({ data: { marked: 1 } });
    await knowiiApiClient.notifications.caughtUp('c1');
    expect(one.mock.calls[0]?.[1]).toMatchObject({ body: JSON.stringify({ community: 'c1' }) });
  });

  it('reads, follows, stops and mutes a post thread', async () => {
    const state = { data: { state: 'following', reason: 'manual' } };
    const calls: [() => Promise<unknown>, string, string][] = [
      [() => knowiiApiClient.threads.getPost('c1', 's1', 'p1'), 'GET', '/api/v1/communities/c1/spaces/s1/posts/p1/follow'],
      [() => knowiiApiClient.threads.followPost('c1', 's1', 'p1'), 'PUT', '/api/v1/communities/c1/spaces/s1/posts/p1/follow'],
      [() => knowiiApiClient.threads.stopPost('c1', 's1', 'p1'), 'DELETE', '/api/v1/communities/c1/spaces/s1/posts/p1/follow'],
      [() => knowiiApiClient.threads.mutePost('c1', 's1', 'p1'), 'PUT', '/api/v1/communities/c1/spaces/s1/posts/p1/mute'],
    ];
    for (const [call, method, url] of calls) {
      const fetchMock = answer(state);
      const result = await call();
      expect(fetchMock.mock.calls[0]).toEqual([url, expect.objectContaining({ method })]);
      expect(result).toMatchObject({ ok: true, data: { state: 'following', reason: 'manual' } });
    }
  });

  it('reads, follows, stops and mutes a chat thread', async () => {
    const calls: [() => Promise<unknown>, string, string][] = [
      [() => knowiiApiClient.threads.getMessage('c1', 's1', 'm1'), 'GET', '/api/v1/communities/c1/spaces/s1/messages/m1/follow'],
      [() => knowiiApiClient.threads.followMessage('c1', 's1', 'm1'), 'PUT', '/api/v1/communities/c1/spaces/s1/messages/m1/follow'],
      [() => knowiiApiClient.threads.stopMessage('c1', 's1', 'm1'), 'DELETE', '/api/v1/communities/c1/spaces/s1/messages/m1/follow'],
      [() => knowiiApiClient.threads.muteMessage('c1', 's1', 'm1'), 'PUT', '/api/v1/communities/c1/spaces/s1/messages/m1/mute'],
    ];
    for (const [call, method, url] of calls) {
      const fetchMock = answer({ data: { state: null, reason: null } });
      const result = await call();
      expect(fetchMock.mock.calls[0]).toEqual([url, expect.objectContaining({ method })]);
      expect(result).toMatchObject({ ok: true, data: { state: null, reason: null } });
    }
  });

  it('refuses a follow state that the server could not have sent', async () => {
    answer({ data: { state: 'snoozed', reason: null } });

    expect(await knowiiApiClient.threads.getPost('c1', 's1', 'p1')).toMatchObject({ ok: false });
  });

  it('reads, chooses and resets the level of a space, and reads the levels of a community', async () => {
    const level = { space_cuid: 's1', level: 'none', chosen: true, default: 'mine' };
    const one = answer({ data: level });
    await knowiiApiClient.notificationLevels.get('c1', 's1');
    expect(one.mock.calls[0]).toEqual(['/api/v1/communities/c1/spaces/s1/notification-level', expect.objectContaining({ method: 'GET' })]);

    const set = answer({ data: level });
    const chosen = await knowiiApiClient.notificationLevels.set('c1', 's1', 'none');
    expect(set.mock.calls[0]).toEqual([
      '/api/v1/communities/c1/spaces/s1/notification-level',
      expect.objectContaining({ method: 'PUT', body: JSON.stringify({ level: 'none' }) }),
    ]);
    expect(chosen.ok && chosen.data.level).toBe('none');

    const reset = answer({ data: { ...level, level: 'mine', chosen: false } });
    await knowiiApiClient.notificationLevels.reset('c1', 's1');
    expect(reset.mock.calls[0]?.[1]).toMatchObject({ method: 'DELETE' });

    const all = answer({
      data: [
        { ...level, space_name: 'Open', space_slug: 'open', space_type: 'discussion', space_group_cuid: 'g1', space_group_name: 'Main' },
      ],
    });
    const levels = await knowiiApiClient.notificationLevels.forCommunity('c1');
    expect(all.mock.calls[0]?.[0]).toBe('/api/v1/communities/c1/notification-levels');
    expect(levels.ok && levels.data[0]).toMatchObject({ space_group_name: 'Main', space_type: 'discussion' });
  });

  it('sends the default level of a space, and "notify now" with a new post', async () => {
    const space = answer({ data: {} });
    await knowiiApiClient.communities.spaces.update('c1', 's1', { default_notification_level: 'all' });
    expect(space.mock.calls[0]?.[1]).toMatchObject({ method: 'PATCH', body: JSON.stringify({ default_notification_level: 'all' }) });

    const post = answer({ data: {} });
    await knowiiApiClient.communities.posts.create('c1', 's1', { title: 'Hello', body: 'World', notify_now: true });
    expect(post.mock.calls[0]?.[1]).toMatchObject({ body: JSON.stringify({ title: 'Hello', body: 'World', notify_now: true }) });
  });
});
