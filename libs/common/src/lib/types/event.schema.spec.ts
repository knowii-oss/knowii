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
  my_answer: 'yes',
  can_answer: true,
  replay_expected: true,
  places_left: 28,
  capacity: null,
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
    const event = eventSchema.parse({
      ...withoutSpace,
      can_view_location: false,
      meeting_url: null,
      host: null,
      my_answer: null,
      can_answer: false,
      places_left: null,
      capacity: null,
    });

    // The answers (#1515): null, false, the boolean, null, null
    expect(event.my_answer).toBeNull();
    expect(event.can_answer).toBe(false);
    expect(event.replay_expected).toBe(true);
    expect(event.places_left).toBeNull();
    expect(event.capacity).toBeNull();
    expect(event.can_view_location).toBe(false);
    expect(event.meeting_url).toBeNull();
    expect(event.host).toBeNull();
    expect(event.space).toBeUndefined();
  });

  it('reads the five fields of the answers: the answer of the reader, the places that are left, and the limit for the host', () => {
    const member = eventSchema.parse(fromServer);
    expect(member.my_answer).toBe('yes');
    expect(member.can_answer).toBe(true);
    expect(member.replay_expected).toBe(true);
    expect(member.places_left).toBe(28);
    expect(member.capacity).toBeNull();

    const host = eventSchema.parse({ ...fromServer, my_answer: null, places_left: 0, capacity: 40 });
    expect(host.my_answer).toBeNull();
    expect(host.places_left).toBe(0);
    expect(host.capacity).toBe(40);
  });

  it('refuses an answer that does not exist, a negative number of places and an event without the five fields', () => {
    expect(eventSchema.safeParse({ ...fromServer, my_answer: 'maybe' }).success).toBe(false);
    expect(eventSchema.safeParse({ ...fromServer, places_left: -1 }).success).toBe(false);
    expect(eventSchema.safeParse({ ...fromServer, capacity: 0 }).success).toBe(false);
    for (const field of ['my_answer', 'can_answer', 'replay_expected', 'places_left', 'capacity'] as const) {
      const { [field]: _dropped, ...without } = fromServer;
      expect(eventSchema.safeParse(without).success, field).toBe(false);
    }
  });

  it('knows the three states and the two visibilities of the address, and nothing else', () => {
    expect(eventStateSchema.options).toEqual(['draft', 'published', 'cancelled']);
    expect(eventLocationVisibilitySchema.options).toEqual(['members', 'going']);
    expect(eventSchema.safeParse({ ...fromServer, state: 'past' }).success).toBe(false);
    expect(eventSchema.safeParse({ ...fromServer, location_visibility: 'everyone' }).success).toBe(false);
  });

  it('reads the community of an event when the read gives it, and an event without it (#1515)', () => {
    const community = { cuid: 'c1', slug: 'example-community', name: 'Example community' };

    expect(eventSchema.parse({ ...fromServer, community }).community).toEqual(community);
    expect(eventSchema.parse(fromServer).community).toBeUndefined();
    expect(eventSchema.safeParse({ ...fromServer, community: { cuid: 'c1' } }).success).toBe(false);
  });
});
