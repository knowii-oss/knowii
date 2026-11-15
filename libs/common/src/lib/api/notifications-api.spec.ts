import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';

const notification = {
  cuid: 'n1',
  kind: 'post_comment',
  level: 1,
  community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
  space: { cuid: 's1', slug: 'open', name: 'Open discussion' },
  actor: null,
  actors: [],
  others_count: 0,
  title: null,
  excerpt: '',
  target: { post_slug: null, comment_cuid: null, chat_message_cuid: null, thread_cuid: null, resource_cuid: null, resource_slug: null },
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
