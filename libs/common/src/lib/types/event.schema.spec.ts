import { describe, expect, it } from 'vitest';
import { eventLocationVisibilitySchema, eventSchema, eventStateSchema } from './event.schema';

// The shape that EventResource.php sends, for a member who may read the meeting address
const fromServer = {
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

describe('eventSchema (#1513)', () => {
  it('reads an event of the server, with the start and the end as instants', () => {
    const event = eventSchema.parse(fromServer);

    expect(event.starts_at.toISOString()).toBe('2026-11-18T16:00:00.000Z');
    expect(event.ends_at.toISOString()).toBe('2026-11-18T17:00:00.000Z');
    expect(event.time_zone).toBe('Europe/Brussels');
    expect(event.host?.name).toBe('Ada');
  });

  it('reads the cut of a visitor: no address, no place, a deleted host, no space', () => {
    const { space: _space, ...withoutSpace } = fromServer;
    const event = eventSchema.parse({ ...withoutSpace, can_view_location: false, meeting_url: null, host: null });

    expect(event.can_view_location).toBe(false);
    expect(event.meeting_url).toBeNull();
    expect(event.host).toBeNull();
    expect(event.space).toBeUndefined();
  });

  it('knows the three states and the two visibilities of the address, and nothing else', () => {
    expect(eventStateSchema.options).toEqual(['draft', 'published', 'cancelled']);
    expect(eventLocationVisibilitySchema.options).toEqual(['members', 'going']);
    expect(eventSchema.safeParse({ ...fromServer, state: 'past' }).success).toBe(false);
    expect(eventSchema.safeParse({ ...fromServer, location_visibility: 'everyone' }).success).toBe(false);
  });
});
