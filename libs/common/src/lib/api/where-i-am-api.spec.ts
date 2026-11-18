import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';

const line = {
  kind: 'announcement',
  title: 'Maintenance on Sunday',
  excerpt: 'We move the servers.',
  space: { cuid: 's1', slug: 'news', name: 'News' },
  author: { cuid: 'a1', name: 'Anna', username: 'anna', avatar_url: null },
  date: '2026-11-14T09:00:00Z',
  target: {
    post_slug: 'maintenance',
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
};

function answer(body: unknown, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve(body) });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('the calls of "where I am" (#1476, ADR 0048)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('loads the count with the conversations that have new messages, and tolerates an answer without them', async () => {
    answer({ data: { count: 4, messages: 2 } });
    const result = await knowiiApiClient.notifications.count();
    expect(result.ok && result.data).toEqual({ count: 4, messages: 2 });

    answer({ data: { count: 4 } });
    const old = await knowiiApiClient.notifications.count();
    expect(old.ok && old.data.messages).toBe(0);
  });

  it('asks for the catch-up of a community since a date, and parses the lines', async () => {
    const fetchMock = answer({ data: { lines: [line], has_more: true } });

    const result = await knowiiApiClient.notifications.catchUp('c1', '2026-11-01');

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/communities/c1/catch-up?since=2026-11-01');
    expect(result.ok && result.data.has_more).toBe(true);
    expect(result.ok && result.data.lines[0]).toMatchObject({ kind: 'announcement', title: 'Maintenance on Sunday' });
    expect(result.ok && result.data.lines[0]?.date).toBeInstanceOf(Date);
  });

  it('fails without throwing when the catch-up has an unknown kind', async () => {
    answer({ data: { lines: [{ ...line, kind: 'count' }], has_more: false } });

    expect((await knowiiApiClient.notifications.catchUp('c1', '2026-11-01')).ok).toBe(false);
  });

  it('visits a space with PUT', async () => {
    const fetchMock = answer({ data: null });

    await knowiiApiClient.notifications.visitSpace('c1', 's1');

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/communities/c1/spaces/s1/visit');
    expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ method: 'PUT' });
  });

  it('sets the time zone with PUT and gives back the own account', async () => {
    const fetchMock = answer({ data: { cuid: 'u1', time_zone: 'Europe/Brussels', notification_popups: true } });

    const result = await knowiiApiClient.users.setTimeZone('Europe/Brussels');

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/user/time-zone');
    expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ method: 'PUT', body: JSON.stringify({ time_zone: 'Europe/Brussels' }) });
    expect(result.ok && result.data.time_zone).toBe('Europe/Brussels');
  });

  it('returns the field errors of an invalid time zone', async () => {
    answer({ message: 'The time zone is invalid', errors: { time_zone: ['Invalid'] } }, 422);

    const result = await knowiiApiClient.users.setTimeZone('Nowhere/Land');

    expect(result.ok).toBe(false);
    expect(!result.ok && result.status).toBe(422);
  });

  it('sets the pop-up switch with PUT', async () => {
    const fetchMock = answer({ data: { cuid: 'u1', time_zone: null, notification_popups: false } });

    const result = await knowiiApiClient.users.setNotificationPopups(false);

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/user/notification-settings');
    expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ method: 'PUT', body: JSON.stringify({ popups: false }) });
    expect(result.ok && result.data.notification_popups).toBe(false);
  });
});
