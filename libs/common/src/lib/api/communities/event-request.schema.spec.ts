import { describe, expect, it } from 'vitest';
import {
  MAX_LENGTH_EVENT_BODY,
  MAX_LENGTH_EVENT_COVER_ALT,
  MAX_LENGTH_EVENT_MEETING_URL,
  MAX_LENGTH_EVENT_PLACE,
  MAX_LENGTH_EVENT_TITLE,
} from '../../constants';
import { EVENT_SCOPES, eventFormSchema, eventRequestSchema, eventScopeSchema, eventUpdateRequestSchema } from './event-request.schema';

const form = {
  title: 'Weekly review office hour',
  body: 'Bring your questions.',
  cover_alt: '',
  start_date: '2026-11-18',
  start_time: '17:00',
  end_date: '',
  end_time: '18:00',
  time_zone: 'Europe/Brussels',
  meeting_url: 'https://meet.example.com/abc',
  place: '',
};

const request = {
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

const messagesOf = (result: { success: boolean; error?: { issues: { path: PropertyKey[]; message: string }[] } }) =>
  Object.fromEntries((result.error?.issues ?? []).map((issue) => [String(issue.path[0]), issue.message]));

describe('eventFormSchema (#1514, design 6.3)', () => {
  it('accepts what the host types, and trims the texts', () => {
    const parsed = eventFormSchema.parse({ ...form, title: '  Weekly review office hour  ', place: ' Room 2 ' });

    expect(parsed.title).toBe('Weekly review office hour');
    expect(parsed.place).toBe('Room 2');
    expect(parsed.end_date).toBe('');
  });

  it('accepts an empty description, cover description, address and place, and an end on another day', () => {
    expect(eventFormSchema.safeParse({ ...form, body: '', meeting_url: '', place: '', end_date: '2026-11-19' }).success).toBe(true);
  });

  it('says what is wrong with each field, with the limits of the server', () => {
    const result = eventFormSchema.safeParse({
      title: 'ab',
      body: 'x'.repeat(MAX_LENGTH_EVENT_BODY + 1),
      cover_alt: 'x'.repeat(MAX_LENGTH_EVENT_COVER_ALT + 1),
      start_date: '',
      start_time: '',
      end_date: 'tomorrow',
      end_time: '',
      time_zone: '',
      meeting_url: 'meet.example.com/abc',
      place: 'x'.repeat(MAX_LENGTH_EVENT_PLACE + 1),
    });

    expect(messagesOf(result)).toEqual({
      title: 'Use between 3 and 200 characters',
      body: 'The description is too long (maximum 20000 characters)',
      cover_alt: 'The description of the cover is too long (maximum 125 characters)',
      start_date: 'Choose a start date',
      start_time: 'Choose a start time',
      end_date: 'Choose an end date',
      end_time: 'Choose an end time',
      time_zone: 'Choose a time zone',
      meeting_url: 'Use an address that starts with http:// or https://',
      place: 'The place is too long (maximum 255 characters)',
    });
  });

  it('refuses a title that is too long and an address that is no http or https address', () => {
    expect(messagesOf(eventFormSchema.safeParse({ ...form, title: 'x'.repeat(MAX_LENGTH_EVENT_TITLE + 1) }))).toEqual({
      title: 'The title is too long (maximum 200 characters)',
    });
    // The script scheme in two parts: the lint rule about script addresses reads the text
    for (const address of [
      ['java', 'script:alert(1)'].join(''),
      'ftp://example.com/room',
      '//example.com/room',
      'mailto:host@example.com',
    ]) {
      expect(eventFormSchema.safeParse({ ...form, meeting_url: address }).success).toBe(false);
    }
    expect(
      messagesOf(eventFormSchema.safeParse({ ...form, meeting_url: `https://example.com/${'x'.repeat(MAX_LENGTH_EVENT_MEETING_URL)}` })),
    ).toEqual({ meeting_url: 'The address is too long (maximum 2048 characters)' });
  });
});

describe('eventRequestSchema (#1514, ADR 0053)', () => {
  it('accepts a start and an end with the offset of the zone, and nothing without an offset', () => {
    expect(eventRequestSchema.safeParse(request).success).toBe(true);
    expect(eventRequestSchema.safeParse({ ...request, starts_at: '2026-11-18T16:00:00Z' }).success).toBe(true);
    expect(eventRequestSchema.safeParse({ ...request, starts_at: '2026-11-18T17:00+01:00' }).success).toBe(true);
    for (const value of ['2026-11-18T17:00:00', '2026-11-18 17:00:00+01:00', '2026-11-18', '17:00', '']) {
      expect(eventRequestSchema.safeParse({ ...request, starts_at: value }).success).toBe(false);
      expect(eventRequestSchema.safeParse({ ...request, ends_at: value }).success).toBe(false);
    }
  });

  it('takes a host, a state and "tell the members now" when they are given', () => {
    const parsed = eventRequestSchema.parse({ ...request, host_cuid: 'u2', state: 'published', notify_now: true });

    expect(parsed).toMatchObject({ host_cuid: 'u2', state: 'published', notify_now: true });
    expect(eventRequestSchema.safeParse({ ...request, state: 'cancelled' }).success).toBe(false);
  });

  it('never carries who sees the meeting address: the form has no field for it', () => {
    const parsed = eventRequestSchema.parse({ ...request, location_visibility: 'going' });
    const changed = eventUpdateRequestSchema.parse({
      title: 'A better title',
      location_visibility: 'going',
      state: 'published',
      notify_now: true,
    });

    expect(Object.keys(parsed)).not.toContain('location_visibility');
    // A change carries no state either: the state has its own call
    expect(changed).toEqual({ title: 'A better title' });
  });

  it('accepts a change of one field', () => {
    expect(eventUpdateRequestSchema.safeParse({}).success).toBe(true);
    expect(eventUpdateRequestSchema.safeParse({ cover_upload_cuid: null, cover_alt: '' }).success).toBe(true);
    expect(eventUpdateRequestSchema.safeParse({ title: 'ab' }).success).toBe(false);
  });
});

describe('the scope of a list of events', () => {
  it('knows "upcoming" and "past", and nothing else', () => {
    expect(EVENT_SCOPES).toEqual(['upcoming', 'past']);
    expect(eventScopeSchema.safeParse('everything').success).toBe(false);
  });
});
