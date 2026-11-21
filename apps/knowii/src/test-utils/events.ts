import type { Community, Pagination, Space, SpaceEvent } from '@knowii/common';

/**
 * Data of the specs of the screens of events (#1514). The instant of the journeys of the design: 18 November 2026,
 * 16:00 UTC, which is 17:00 in Brussels, 11:00 in New York and Thursday 00:00 in Singapore.
 */
export const EVENT_START = '2026-11-18T16:00:00.000000Z';
export const EVENT_END = '2026-11-18T17:00:00.000000Z';
// A week before the event
export const EVENT_TEST_NOW = new Date('2026-11-11T09:00:00Z');

export const eventsCommunity = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'private',
  is_member: true,
} as Community;

export const eventsSpace = {
  cuid: 's1',
  name: 'Office hours',
  slug: 'office-hours',
  type: 'events',
  visibility: 'members',
  posting_policy: 'moderators',
  emoji: '',
} as Space;

/**
 * An event as the page gets it from the server: the dates of an Inertia prop are texts (rules.md, Front-end 6)
 */
export function eventFixture(cuid: string, title: string, changes: Partial<Record<keyof SpaceEvent, unknown>> = {}): SpaceEvent {
  return {
    cuid,
    title,
    slug: title.toLowerCase().replaceAll(' ', '-'),
    state: 'published',
    starts_at: EVENT_START,
    ends_at: EVENT_END,
    time_zone: 'Europe/Brussels',
    is_past: false,
    location_visibility: 'members',
    can_view_location: true,
    meeting_url: 'https://meet.example.com/abc',
    place: null,
    // The answers (#1515): a member who did not answer an event without a limit and without a replay
    my_answer: null,
    can_answer: true,
    replay_expected: false,
    places_left: null,
    capacity: null,
    cover: null,
    cover_alt: null,
    host: { cuid: 'u1', name: 'Ada Lovelace', username: 'ada', avatar_url: null },
    space: { cuid: 's1', slug: 'office-hours', name: 'Office hours' },
    // The community of the event, as "My events" gets it (#1515)
    community: { cuid: 'c1', slug: 'tests', name: 'Tests' },
    body: null,
    body_html: null,
    created_at: '2026-11-10T10:00:00.000000Z',
    updated_at: '2026-11-10T10:00:00.000000Z',
    ...changes,
  } as unknown as SpaceEvent;
}

export function eventsPagination(nextOffset: number | null, totalCount: number): Pagination {
  return { limit: 20, offset: 0, previousOffset: null, nextOffset, currentPage: 1, pageCount: nextOffset === null ? 1 : 2, totalCount };
}

/**
 * The address of a page of events, as the route helper of the application builds it
 */
export function eventsRoute(name: string, params: Record<string, string> = {}): string {
  const space = `/community/${params['communitySlug']}/space/${params['spaceSlug']}`;
  switch (name) {
    case 'events.show':
      return `${space}/event/${params['eventSlug']}`;
    case 'events.edit':
      return `${space}/event/${params['eventSlug']}/edit`;
    case 'events.create':
      return `${space}/events/new`;
    case 'spaces.show':
      return space;
    case 'communities.show':
      return `/community/${params['communitySlug']}`;
    case 'events.index':
      return '/events';
    case 'members.show':
      return `/community/${params['communitySlug']}/member/${params['username']}`;
    default:
      return `/${name}`;
  }
}
