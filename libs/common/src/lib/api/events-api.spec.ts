import { afterEach, describe, expect, it, vi } from 'vitest';
import { knowiiApiClient } from './knowii-api-client';
import type { EventRequest } from './communities/event-request.schema';

// The shape that EventResource.php sends
const event = {
  cuid: 'e1',
  title: 'Weekly review office hour',
  slug: 'weekly-review-office-hour',
  state: 'published',
  starts_at: '2026-11-18T16:00:00.000000Z',
  ends_at: '2026-11-18T17:00:00.000000Z',
  time_zone: 'Europe/Brussels',
  is_past: false,
  location_visibility: 'members',
  can_view_location: true,
  meeting_url: 'https://meet.example.com/abc',
  place: null,
  cover: null,
  cover_alt: null,
  host: { cuid: 'u1', name: 'Ada', username: 'ada', avatar_url: null },
  space: { cuid: 's1', slug: 'office-hours', name: 'Office hours' },
  body: null,
  body_html: null,
  created_at: '2026-11-10T10:00:00.000000Z',
  updated_at: '2026-11-10T10:00:00.000000Z',
};

const pagination = { limit: 20, offset: 0, previousOffset: null, nextOffset: 20, currentPage: 1, pageCount: 2, totalCount: 21 };

const input: EventRequest = {
  title: 'Weekly review office hour',
  body: '',
  starts_at: '2026-11-18T17:00:00+01:00',
  ends_at: '2026-11-18T18:00:00+01:00',
  time_zone: 'Europe/Brussels',
  meeting_url: null,
  place: null,
  cover_upload_cuid: null,
  cover_alt: null,
};

function answer(body: unknown, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve(body) });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

const callOf = (fetchMock: ReturnType<typeof answer>) => ({
  url: fetchMock.mock.calls[0]?.[0] as string,
  options: fetchMock.mock.calls[0]?.[1] as { method: string; body?: string },
});

describe('the calls of events (#1514, ADR 0053)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('lists a page of the events of a space, with the scope, and parses the dates and the pagination', async () => {
    const fetchMock = answer({ message: 'Events', data: [event], metadata: { pagination } });

    const result = await knowiiApiClient.communities.events.list('c1', 's1', 'past', 20, 40);

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events?scope=past&limit=20&offset=40');
    expect(callOf(fetchMock).options.method).toBe('GET');
    expect(result.ok && result.data.items[0]?.starts_at).toBeInstanceOf(Date);
    expect(result.ok && result.data.pagination.nextOffset).toBe(20);
  });

  it('lists the first 20 upcoming events by default', async () => {
    const fetchMock = answer({ data: [], metadata: { pagination: { ...pagination, nextOffset: null, totalCount: 0 } } });

    const result = await knowiiApiClient.communities.events.list('c1', 's1', 'upcoming');

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events?scope=upcoming&limit=20&offset=0');
    expect(result.ok && result.data.items).toEqual([]);
  });

  it('keeps the message of the server when a list is refused, and fails without throwing on a broken answer', async () => {
    answer({ message: 'Too many requests. Wait a minute.' }, 429);
    expect(await knowiiApiClient.communities.events.list('c1', 's1', 'upcoming')).toEqual({
      ok: false,
      status: 429,
      message: 'Too many requests. Wait a minute.',
      errors: {},
    });

    answer({ data: [{ ...event, state: 'unknown' }], metadata: { pagination } });
    expect(await knowiiApiClient.communities.events.list('c1', 's1', 'upcoming')).toMatchObject({
      ok: false,
      message: 'Failed to load the events.',
    });

    answer({ data: [event] });
    expect((await knowiiApiClient.communities.events.list('c1', 's1', 'upcoming')).ok).toBe(false);

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    expect(await knowiiApiClient.communities.events.list('c1', 's1', 'upcoming')).toMatchObject({ ok: false, status: 0 });
  });

  it('lists the upcoming events of the community', async () => {
    const fetchMock = answer({ data: [event], metadata: { pagination } });

    const result = await knowiiApiClient.communities.events.listUpcoming('c1', 3);

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/events?limit=3&offset=0');
    expect(result.ok && result.data.items[0]?.space?.slug).toBe('office-hours');
  });

  it('gets one event', async () => {
    const fetchMock = answer({ data: { ...event, body: 'Bring **questions**', body_html: '<p>Bring <strong>questions</strong></p>' } });

    const result = await knowiiApiClient.communities.events.get('c1', 's1', 'e1');

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events/e1');
    expect(callOf(fetchMock).options.method).toBe('GET');
    expect(result.ok && result.data.body).toBe('Bring **questions**');
  });

  it('answers a draft of another host like an event that does not exist', async () => {
    answer({ message: 'Not found' }, 404);

    expect(await knowiiApiClient.communities.events.get('c1', 's1', 'e1')).toMatchObject({ ok: false, status: 404 });
  });

  it('creates an event with POST and the times with their offset', async () => {
    const fetchMock = answer({ message: 'Event created', data: { ...event, state: 'draft' } }, 201);

    const result = await knowiiApiClient.communities.events.create('c1', 's1', { ...input, state: 'published', notify_now: true });

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events');
    expect(callOf(fetchMock).options.method).toBe('POST');
    expect(JSON.parse(callOf(fetchMock).options.body ?? '{}')).toEqual({ ...input, state: 'published', notify_now: true });
    expect(result.ok && result.data.state).toBe('draft');
  });

  it('gives the field errors of a refused time', async () => {
    answer(
      { message: 'Invalid', errors: { starts_at: ['This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.'] } },
      400,
    );

    const result = await knowiiApiClient.communities.events.create('c1', 's1', input);

    expect(!result.ok && result.status).toBe(400);
    expect(!result.ok && result.errors['starts_at']?.[0]).toBe(
      'This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.',
    );
  });

  it('changes an event with PATCH (in uppercase)', async () => {
    const fetchMock = answer({ data: { ...event, title: 'A better title' } });

    const result = await knowiiApiClient.communities.events.update('c1', 's1', 'e1', { title: 'A better title' });

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events/e1');
    expect(callOf(fetchMock).options).toMatchObject({ method: 'PATCH', body: JSON.stringify({ title: 'A better title' }) });
    expect(result.ok && result.data.title).toBe('A better title');
  });

  it('sets the state with PUT, and "tell the members now" only when it is asked', async () => {
    let fetchMock = answer({ data: { ...event, state: 'cancelled' } });
    const cancelled = await knowiiApiClient.communities.events.setState('c1', 's1', 'e1', 'cancelled');

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events/e1/state');
    expect(callOf(fetchMock).options).toMatchObject({ method: 'PUT', body: JSON.stringify({ state: 'cancelled' }) });
    expect(cancelled.ok && cancelled.data.state).toBe('cancelled');

    fetchMock = answer({ data: event });
    await knowiiApiClient.communities.events.setState('c1', 's1', 'e1', 'published', true);
    expect(callOf(fetchMock).options.body).toBe(JSON.stringify({ state: 'published', notify_now: true }));
  });

  it('keeps the message of a refused change of state (422)', async () => {
    answer({ message: 'An event that started cannot go back to draft. Cancel it instead.' }, 422);

    expect(await knowiiApiClient.communities.events.setState('c1', 's1', 'e1', 'draft')).toMatchObject({
      ok: false,
      status: 422,
      message: 'An event that started cannot go back to draft. Cancel it instead.',
    });
  });

  it('duplicates an event with POST and gives the copy', async () => {
    const fetchMock = answer({ data: { ...event, cuid: 'e2', slug: 'weekly-review-office-hour-2', state: 'draft' } }, 201);

    const result = await knowiiApiClient.communities.events.duplicate('c1', 's1', 'e1');

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events/e1/duplicate');
    expect(callOf(fetchMock).options.method).toBe('POST');
    expect(callOf(fetchMock).options.body).toBeUndefined();
    expect(result.ok && result.data.slug).toBe('weekly-review-office-hour-2');
  });

  it('deletes an event with DELETE', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 204, json: () => Promise.reject(new Error('no content')) });
    vi.stubGlobal('fetch', fetchMock);

    const result = await knowiiApiClient.communities.events.delete('c1', 's1', 'e1');

    expect(callOf(fetchMock).url).toBe('/api/v1/communities/c1/spaces/s1/events/e1');
    expect(callOf(fetchMock).options.method).toBe('DELETE');
    expect(result.ok).toBe(true);
  });
});
