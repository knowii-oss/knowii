import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  browserTimeZone,
  currentTimeZoneName,
  formatEventRange,
  formatEventStart,
  formatFullDateTime,
  instantToZonedDateTime,
  isKnownTimeZone,
  knownTimeZones,
  readsTheSameInZones,
  timeZoneInWords,
  timeZoneOptions,
  toDate,
  utcOffsetLabel,
  zonedDateTimeToIso,
} from './time-zone.fn';

describe('the time zones (#1476)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('knows an IANA name and UTC, and refuses the rest', () => {
    expect(isKnownTimeZone('Europe/Brussels')).toBe(true);
    expect(isKnownTimeZone('UTC')).toBe(true);
    expect(isKnownTimeZone('Nowhere/Land')).toBe(false);
    expect(isKnownTimeZone('')).toBe(false);
    expect(isKnownTimeZone(null)).toBe(false);
  });

  it('gives null when the browser reports no zone, and never throws', () => {
    vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(() => {
      throw new Error('no Intl');
    });
    expect(browserTimeZone()).toBeNull();
  });

  it('gives the current name of a zone for an older name that a browser still gives (#1678)', () => {
    expect(currentTimeZoneName('Asia/Calcutta')).toBe('Asia/Kolkata');
    expect(currentTimeZoneName('Europe/Kiev')).toBe('Europe/Kyiv');
    expect(currentTimeZoneName('Asia/Saigon')).toBe('Asia/Ho_Chi_Minh');
    expect(currentTimeZoneName('America/Buenos_Aires')).toBe('America/Argentina/Buenos_Aires');
    // A current name, UTC and a text that is no zone come back as they are
    expect(currentTimeZoneName('Europe/Brussels')).toBe('Europe/Brussels');
    expect(currentTimeZoneName('UTC')).toBe('UTC');
    expect(currentTimeZoneName('Nowhere/Land')).toBe('Nowhere/Land');
    expect(currentTimeZoneName('toString')).toBe('toString');
  });

  it('reports the zone of the browser under its current name (#1678)', () => {
    // A browser that still gives the older name of the zone of India
    const options = new Intl.DateTimeFormat().resolvedOptions();
    vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({ ...options, timeZone: 'Asia/Calcutta' });

    expect(browserTimeZone()).toBe('Asia/Kolkata');
  });

  it('lists each zone one time, under its current name, also when the account or the browser gives an older name (#1678)', () => {
    const zones = knownTimeZones(['Asia/Calcutta', 'Europe/Kiev']);

    expect(zones).toContain('Asia/Kolkata');
    expect(zones).toContain('Europe/Kyiv');
    expect(zones).not.toContain('Asia/Calcutta');
    expect(zones).not.toContain('Europe/Kiev');
    expect(zones).not.toContain('Asia/Saigon');
    expect(new Set(zones).size).toBe(zones.length);

    const kolkata = timeZoneOptions().find((option) => option.value === 'Asia/Kolkata');
    expect(kolkata?.label).toBe('Kolkata, Asia (UTC+5:30)');
    // The older name still finds the zone in a search
    expect(kolkata?.keywords).toEqual(['Asia/Kolkata', 'Asia/Calcutta']);
  });

  it('lists the zones of the browser with UTC, sorted, without doubles', () => {
    const zones = knownTimeZones(['Europe/Brussels', 'Europe/Brussels']);

    expect(zones).toContain('UTC');
    expect(zones).toContain('Europe/Brussels');
    expect(zones.filter((zone) => zone === 'Europe/Brussels')).toHaveLength(1);
    expect(zones).toEqual([...zones].sort((left, right) => left.localeCompare(right)));
  });

  it('says a zone in words', () => {
    expect(timeZoneInWords('Europe/Brussels', new Date('2026-11-15T10:00:00Z'))).toBe('Central European Standard Time (Europe/Brussels)');
    expect(timeZoneInWords('Nowhere/Land')).toBe('Nowhere/Land');
  });

  it('shows the full date and hour in the zone given, and names the zone', () => {
    const date = new Date('2026-11-15T10:00:00Z');

    expect(formatFullDateTime(date, 'Asia/Tokyo')).toBe('Nov 15, 2026, 7:00:00 PM GMT+9');
    expect(formatFullDateTime(date, 'UTC')).toContain('10:00:00 AM UTC');
  });

  it('writes the start of an event in a short line, in the zone given, with the zone in words (#1513)', () => {
    // 16:00 UTC: 17:00 in Brussels, 11:00 in New York, 00:00 the next day in Singapore. The weekday comes from the code
    const date = new Date('2026-11-18T16:00:00Z');
    const weekday = (zone: string) => new Intl.DateTimeFormat('en-GB', { weekday: 'short', timeZone: zone }).format(date);
    const zoneName = (zone: string) =>
      new Intl.DateTimeFormat('en-GB', { timeZone: zone, timeZoneName: 'long' })
        .formatToParts(date)
        .find((part) => part.type === 'timeZoneName')?.value;

    // The present of the test: the year of the event is the current year
    const now = new Date('2026-11-10T09:00:00Z');

    expect(formatEventStart(date, 'Europe/Brussels', now)).toBe(
      `${weekday('Europe/Brussels')} 18 Nov, 17:00 ${zoneName('Europe/Brussels')}`,
    );
    expect(formatEventStart(date, 'America/New_York', now)).toBe(
      `${weekday('America/New_York')} 18 Nov, 11:00 ${zoneName('America/New_York')}`,
    );
    expect(formatEventStart(date, 'Asia/Singapore', now)).toBe(`${weekday('Asia/Singapore')} 19 Nov, 00:00 ${zoneName('Asia/Singapore')}`);
    expect(weekday('Asia/Singapore')).not.toBe(weekday('Europe/Brussels'));
    expect(formatEventStart(date, 'Europe/Brussels', now)).toMatch(/Central European/);
  });

  it('writes the year in the short line when it is not the current year, like the long form (#1514)', () => {
    const now = new Date('2026-11-10T09:00:00Z');

    // An event of the next year, and one of the year before (the list "Past")
    expect(formatEventStart(new Date('2027-01-14T22:00:00Z'), 'Europe/Brussels', now)).toBe(
      'Thu 14 Jan 2027, 23:00 Central European Standard Time',
    );
    expect(formatEventStart(new Date('2025-06-12T16:00:00Z'), 'Europe/Brussels', now)).toBe(
      'Thu 12 Jun 2025, 18:00 Central European Summer Time',
    );
    // The current year is the one of the zone: on 31 December at 22:30 UTC, Singapore is in 2027 and Brussels is not
    const newYear = new Date('2026-12-31T22:30:00Z');
    expect(formatEventStart(new Date('2027-01-05T10:00:00Z'), 'Asia/Singapore', newYear)).toBe('Tue 5 Jan, 18:00 Singapore Standard Time');
    expect(formatEventStart(new Date('2027-01-05T10:00:00Z'), 'Europe/Brussels', newYear)).toBe(
      'Tue 5 Jan 2027, 11:00 Central European Standard Time',
    );
    // Without a present, the clock decides
    expect(formatEventStart(new Date(), 'Europe/Brussels')).not.toContain(String(new Date().getUTCFullYear()));
  });

  it('writes the start of an event in the zone of the browser without a known zone', () => {
    const date = new Date('2026-11-18T16:00:00Z');

    expect(formatEventStart(date, null)).toBe(formatEventStart(date, Intl.DateTimeFormat().resolvedOptions().timeZone));
    expect(formatEventStart(date, 'Not/AZone')).toBe(formatEventStart(date, undefined));
  });
});

describe('the entries of the list of time zones (#1666)', () => {
  // The winter in Brussels and in New York, the summer in Santiago
  const winter = new Date('2026-11-15T10:00:00Z');
  const summer = new Date('2026-07-15T10:00:00Z');
  const entry = (zone: string, at: Date = winter) => timeZoneOptions([zone], at).find((option) => option.value === zone);

  it('writes the city, the region and the offset in the label, and the long name of the zone as the description', () => {
    expect(entry('Europe/Brussels')).toEqual({
      value: 'Europe/Brussels',
      label: 'Brussels, Europe (UTC+1)',
      description: 'Central European Standard Time',
      keywords: ['Europe/Brussels'],
    });
    expect(entry('Asia/Tokyo')).toEqual({
      value: 'Asia/Tokyo',
      label: 'Tokyo, Asia (UTC+9)',
      description: 'Japan Standard Time',
      keywords: ['Asia/Tokyo'],
    });
  });

  it('writes the underscores of a city as spaces, and keeps the identifier as the value and as a keyword', () => {
    const newYork = entry('America/New_York');

    expect(newYork?.label).toBe('New York, America (UTC-5)');
    expect(newYork?.value).toBe('America/New_York');
    expect(newYork?.keywords).toEqual(['America/New_York']);
    expect(entry('America/Port-au-Prince')?.label).toBe('Port-au-Prince, America (UTC-5)');
  });

  it('gives the offset at the date asked for: it follows the daylight saving time', () => {
    expect(entry('Europe/Brussels', summer)).toMatchObject({
      label: 'Brussels, Europe (UTC+2)',
      description: 'Central European Summer Time',
    });
    expect(entry('America/New_York', summer)?.label).toBe('New York, America (UTC-4)');
    expect(entry('Asia/Tokyo', summer)?.label).toBe('Tokyo, Asia (UTC+9)');
  });

  it('writes an offset of zero, and an offset with minutes', () => {
    expect(entry('Europe/London')?.label).toBe('London, Europe (UTC+0)');
    expect(entry('Europe/London', summer)?.label).toBe('London, Europe (UTC+1)');
    expect(entry('America/St_Johns')?.label).toBe('St Johns, America (UTC-3:30)');
    expect(entry('Pacific/Chatham')?.label).toBe('Chatham, Pacific (UTC+13:45)');
    expect(entry('Pacific/Kiritimati')?.label).toBe('Kiritimati, Pacific (UTC+14)');
  });

  it('writes a zone of three parts from the city to the region', () => {
    expect(entry('America/Argentina/Buenos_Aires')?.label).toBe('Buenos Aires, Argentina, America (UTC-3)');
  });

  it('writes UTC alone, with its long name', () => {
    expect(entry('UTC')).toEqual({ value: 'UTC', label: 'UTC', description: 'Coordinated Universal Time', keywords: ['UTC'] });
  });

  it('gives no description when Intl has no name for the zone, only its offset', () => {
    const formatToParts = Intl.DateTimeFormat.prototype.formatToParts;
    vi.spyOn(Intl.DateTimeFormat.prototype, 'formatToParts').mockImplementation(function (this: Intl.DateTimeFormat, date) {
      return formatToParts.call(this, date).map((part) => (part.type === 'timeZoneName' ? { ...part, value: 'GMT+01:00' } : part));
    });

    const brussels = entry('Europe/Brussels');
    vi.restoreAllMocks();

    expect(brussels).toEqual({ value: 'Europe/Brussels', label: 'Brussels, Europe (UTC+1)', keywords: ['Europe/Brussels'] });
    expect(brussels).not.toHaveProperty('description');
  });

  it('keeps a zone that the browser does not know, with its place alone', () => {
    expect(entry('Nowhere/Land')).toEqual({ value: 'Nowhere/Land', label: 'Land, Nowhere', keywords: ['Nowhere/Land'] });
  });

  it('gives one entry per known zone, in the order of the known zones, with UTC', () => {
    const options = timeZoneOptions(['Europe/Brussels', null, undefined], winter);

    expect(options.map((option) => option.value)).toEqual(knownTimeZones(['Europe/Brussels']));
    expect(options.length).toBeGreaterThan(100);
    expect(options.map((option) => option.value)).toContain('UTC');
    // Every label is its own: a person can tell two entries apart
    expect(new Set(options.map((option) => option.label)).size).toBe(options.length);
  });
});

describe('the times of events (#1514, design 4, 5 and 11.2)', () => {
  // The instant of the journeys of the design: 18 November 2026, 16:00 UTC. It is a Wednesday (the design writes
  // "Tuesday": the weekday always comes from the date)
  const start = new Date('2026-11-18T16:00:00Z');
  const end = new Date('2026-11-18T17:00:00Z');
  const now = new Date('2026-11-10T09:00:00Z');

  describe('the long form', () => {
    it('writes the same instant in Brussels, in New York and in Singapore, with the zone in words', () => {
      expect(formatEventRange(start, end, 'Europe/Brussels', { now })).toBe(
        'Wednesday 18 November, 17:00 to 18:00 Central European Standard Time',
      );
      expect(formatEventRange(start, end, 'America/New_York', { now })).toBe('Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time');
      // The date follows the zone: Wednesday 17:00 in Brussels is Thursday 00:00 in Singapore
      expect(formatEventRange(start, end, 'Asia/Singapore', { now })).toBe('Thursday 19 November, 00:00 to 01:00 Singapore Standard Time');
    });

    it('accepts the dates of an Inertia prop, which are texts', () => {
      expect(formatEventRange('2026-11-18T16:00:00.000000Z', '2026-11-18T17:00:00.000000Z', 'Europe/Brussels', { now })).toBe(
        formatEventRange(start, end, 'Europe/Brussels', { now }),
      );
      expect(formatEventStart('2026-11-18T16:00:00.000000Z', 'Europe/Brussels')).toBe(formatEventStart(start, 'Europe/Brussels'));
      expect(toDate('2026-11-18T16:00:00.000000Z').toISOString()).toBe('2026-11-18T16:00:00.000Z');
      expect(toDate(start)).toBe(start);
    });

    it('names the day of an end on another day', () => {
      const late = new Date('2026-11-18T22:00:00Z');
      const afterMidnight = new Date('2026-11-19T00:00:00Z');

      expect(formatEventRange(late, afterMidnight, 'Europe/Brussels', { now })).toBe(
        'Wednesday 18 November, 23:00 to Thursday 19 November, 01:00 Central European Standard Time',
      );
      // The same event starts and ends on one day in New York
      expect(formatEventRange(late, afterMidnight, 'America/New_York', { now })).toBe(
        'Wednesday 18 November, 17:00 to 19:00 Eastern Standard Time',
      );
    });

    it('writes the year after the month when it is not the current year', () => {
      expect(formatEventRange(start, end, 'Europe/Brussels', { now: new Date('2027-01-05T09:00:00Z') })).toBe(
        'Wednesday 18 November 2026, 17:00 to 18:00 Central European Standard Time',
      );
      // An end in the next year names its year, in the current year or not
      expect(formatEventRange(new Date('2026-12-31T22:00:00Z'), new Date('2027-01-01T00:00:00Z'), 'Europe/Brussels', { now })).toBe(
        'Thursday 31 December, 23:00 to Friday 1 January 2027, 01:00 Central European Standard Time',
      );
    });

    it('writes the start alone without an end: the time of the host', () => {
      expect(formatEventRange(start, null, 'Europe/Brussels', { now })).toBe('Wednesday 18 November, 17:00 Central European Standard Time');
    });

    it('writes the form of the host with the year and the offset', () => {
      expect(formatEventRange(start, end, 'Europe/Brussels', { year: 'always', offset: true, now })).toBe(
        'Wednesday 18 November 2026, 17:00 to 18:00, Central European Standard Time (UTC+1)',
      );
      expect(formatEventRange(start, end, 'Asia/Kolkata', { year: 'always', offset: true, now })).toBe(
        'Wednesday 18 November 2026, 21:30 to 22:30, India Standard Time (UTC+5:30)',
      );
    });

    it('gives each time its zone words when the clocks change between the start and the end', () => {
      // 31 October 2027 in Brussels: 02:30 in summer time, then one hour and a half later, in winter time
      expect(formatEventRange(new Date('2027-10-31T00:30:00Z'), new Date('2027-10-31T02:00:00Z'), 'Europe/Brussels', { now })).toBe(
        'Sunday 31 October 2027, 02:30 Central European Summer Time to 03:00 Central European Standard Time',
      );
    });

    it('uses the zone of the browser without a known zone, and never throws', () => {
      const browser = Intl.DateTimeFormat().resolvedOptions().timeZone;

      expect(formatEventRange(start, end, null, { now })).toBe(formatEventRange(start, end, browser, { now }));
      expect(formatEventRange(start, end, 'Not/AZone', { now })).toBe(formatEventRange(start, end, undefined, { now }));
      // A value that is no date gives no text
      expect(formatEventRange(new Date('nothing'), end, 'Europe/Brussels', { now: start })).toBe('');
      expect(formatEventRange('nothing', null, 'Europe/Brussels', { now: start })).toBe('');
      expect(formatEventStart('nothing', 'Europe/Brussels')).toBe('');
    });
  });

  describe('the offset in words', () => {
    it('follows the summer time', () => {
      expect(utcOffsetLabel(start, 'Europe/Brussels')).toBe('UTC+1');
      expect(utcOffsetLabel(new Date('2027-07-01T12:00:00Z'), 'Europe/Brussels')).toBe('UTC+2');
      expect(utcOffsetLabel(start, 'America/New_York')).toBe('UTC-5');
      expect(utcOffsetLabel(start, 'Asia/Kolkata')).toBe('UTC+5:30');
      expect(utcOffsetLabel(start, 'UTC')).toBe('UTC+0');
      expect(utcOffsetLabel(start, 'Not/AZone')).toBe('');
    });
  });

  describe('a local date and time to an instant', () => {
    it('gives ISO 8601 with the offset of the zone at that time', () => {
      expect(zonedDateTimeToIso('2026-11-18', '17:00', 'Europe/Brussels')).toEqual({
        kind: 'instant',
        iso: '2026-11-18T17:00:00+01:00',
        offsetMinutes: 60,
      });
      expect(zonedDateTimeToIso('2026-11-18', '11:00', 'America/New_York')).toEqual({
        kind: 'instant',
        iso: '2026-11-18T11:00:00-05:00',
        offsetMinutes: -300,
      });
      expect(zonedDateTimeToIso('2026-11-19', '00:00', 'Asia/Singapore')).toEqual({
        kind: 'instant',
        iso: '2026-11-19T00:00:00+08:00',
        offsetMinutes: 480,
      });
      expect(zonedDateTimeToIso('2026-07-01', '09:15', 'Asia/Kolkata')).toMatchObject({ iso: '2026-07-01T09:15:00+05:30' });
      expect(zonedDateTimeToIso('2026-07-01', '09:15', 'UTC')).toMatchObject({ iso: '2026-07-01T09:15:00+00:00' });
      // The three texts are one instant
      const instants = [
        zonedDateTimeToIso('2026-11-18', '17:00', 'Europe/Brussels'),
        zonedDateTimeToIso('2026-11-18', '11:00', 'America/New_York'),
        zonedDateTimeToIso('2026-11-19', '00:00', 'Asia/Singapore'),
      ].map((result) => (result.kind === 'instant' ? new Date(result.iso).toISOString() : ''));
      expect(instants).toEqual(['2026-11-18T16:00:00.000Z', '2026-11-18T16:00:00.000Z', '2026-11-18T16:00:00.000Z']);
    });

    // A gap in the night: the last time before it is on the same day
    const skipped = { kind: 'skipped', beforeIsDayBefore: false };

    it('refuses a time that the zone skips, with the two times around the gap (28 March 2027, 02:30 in Brussels)', () => {
      expect(zonedDateTimeToIso('2027-03-28', '02:30', 'Europe/Brussels')).toEqual({ ...skipped, before: '01:59', after: '03:00' });
      expect(zonedDateTimeToIso('2027-03-28', '02:00', 'Europe/Brussels')).toEqual({ ...skipped, before: '01:59', after: '03:00' });
      expect(zonedDateTimeToIso('2027-03-28', '02:59', 'Europe/Brussels')).toEqual({ ...skipped, before: '01:59', after: '03:00' });
      // The minutes around the gap exist
      expect(zonedDateTimeToIso('2027-03-28', '01:59', 'Europe/Brussels')).toMatchObject({
        kind: 'instant',
        iso: '2027-03-28T01:59:00+01:00',
      });
      expect(zonedDateTimeToIso('2027-03-28', '03:00', 'Europe/Brussels')).toMatchObject({
        kind: 'instant',
        iso: '2027-03-28T03:00:00+02:00',
      });
      // New York skips another hour, two weeks before
      expect(zonedDateTimeToIso('2027-03-14', '02:30', 'America/New_York')).toEqual({ ...skipped, before: '01:59', after: '03:00' });
      // A gap of 30 minutes (Lord Howe Island)
      expect(zonedDateTimeToIso('2027-10-03', '02:15', 'Australia/Lord_Howe')).toEqual({ ...skipped, before: '01:59', after: '02:30' });
    });

    it('says that the last time before a gap at midnight is on the day before (28 March 2027, 00:30 in the Azores)', () => {
      // The clocks of the Azores go from 00:00 to 01:00: the last minute before the gap is 23:59 of 27 March
      const dayBefore = { kind: 'skipped', before: '23:59', after: '01:00', beforeIsDayBefore: true };
      expect(zonedDateTimeToIso('2027-03-28', '00:30', 'Atlantic/Azores')).toEqual(dayBefore);
      expect(zonedDateTimeToIso('2027-03-28', '00:00', 'Atlantic/Azores')).toEqual(dayBefore);
      // The minutes around the gap exist
      expect(zonedDateTimeToIso('2027-03-27', '23:59', 'Atlantic/Azores')).toMatchObject({ kind: 'instant' });
      expect(zonedDateTimeToIso('2027-03-28', '01:00', 'Atlantic/Azores')).toMatchObject({ kind: 'instant' });
    });

    it('gives both instants of a time that exists twice, the summer time first (31 October 2027, 02:30 in Brussels)', () => {
      expect(zonedDateTimeToIso('2027-10-31', '02:30', 'Europe/Brussels')).toEqual({
        kind: 'twice',
        summer: { iso: '2027-10-31T02:30:00+02:00', offsetMinutes: 120 },
        winter: { iso: '2027-10-31T02:30:00+01:00', offsetMinutes: 60 },
      });
      // 03:00 exists one time again
      expect(zonedDateTimeToIso('2027-10-31', '03:00', 'Europe/Brussels')).toMatchObject({
        kind: 'instant',
        iso: '2027-10-31T03:00:00+01:00',
      });
      expect(zonedDateTimeToIso('2027-10-31', '01:59', 'Europe/Brussels')).toMatchObject({
        kind: 'instant',
        iso: '2027-10-31T01:59:00+02:00',
      });
    });

    it('refuses what is no date, no time or no zone', () => {
      expect(zonedDateTimeToIso('', '17:00', 'Europe/Brussels')).toEqual({ kind: 'invalid' });
      expect(zonedDateTimeToIso('2026-11-18', '', 'Europe/Brussels')).toEqual({ kind: 'invalid' });
      expect(zonedDateTimeToIso('2026-11-31', '17:00', 'Europe/Brussels')).toEqual({ kind: 'invalid' });
      expect(zonedDateTimeToIso('2026-11-18', '25:00', 'Europe/Brussels')).toEqual({ kind: 'invalid' });
      expect(zonedDateTimeToIso('18/11/2026', '17:00', 'Europe/Brussels')).toEqual({ kind: 'invalid' });
      expect(zonedDateTimeToIso('2026-11-18', '17:00', 'Not/AZone')).toEqual({ kind: 'invalid' });
    });

    it('keeps the hour of the host over a change of the clocks: the weekly office hour (journey 5.I)', () => {
      // 23 March 2027, 17:00 in Brussels (UTC+1) is 12:00 in New York, which is in summer time since 14 March
      const first = zonedDateTimeToIso('2027-03-23', '17:00', 'Europe/Brussels');
      // One week later Brussels is in summer time too (since 28 March): 17:00 is one hour earlier in New York
      const second = zonedDateTimeToIso('2027-03-30', '17:00', 'Europe/Brussels');

      expect(first).toMatchObject({ kind: 'instant', iso: '2027-03-23T17:00:00+01:00' });
      expect(second).toMatchObject({ kind: 'instant', iso: '2027-03-30T17:00:00+02:00' });
      const instant = (result: typeof first) => new Date(result.kind === 'instant' ? result.iso : '');
      expect(instant(first).toISOString()).toBe('2027-03-23T16:00:00.000Z');
      expect(instant(second).toISOString()).toBe('2027-03-30T15:00:00.000Z');
      expect(formatEventRange(instant(first), null, 'America/New_York', { now })).toBe(
        'Tuesday 23 March 2027, 12:00 Eastern Daylight Time',
      );
      expect(formatEventRange(instant(second), null, 'America/New_York', { now })).toBe(
        'Tuesday 30 March 2027, 11:00 Eastern Daylight Time',
      );
      expect(formatEventRange(instant(second), null, 'Europe/Brussels', { now })).toBe(
        'Tuesday 30 March 2027, 17:00 Central European Summer Time',
      );
    });
  });

  describe('an instant to a local date and time', () => {
    it('fills the fields of the form in the zone of the event', () => {
      expect(instantToZonedDateTime(start, 'Europe/Brussels')).toEqual({ date: '2026-11-18', time: '17:00', offsetMinutes: 60 });
      expect(instantToZonedDateTime('2026-11-18T16:00:00.000000Z', 'Asia/Singapore')).toEqual({
        date: '2026-11-19',
        time: '00:00',
        offsetMinutes: 480,
      });
      expect(instantToZonedDateTime(start, 'America/New_York')).toEqual({ date: '2026-11-18', time: '11:00', offsetMinutes: -300 });
    });

    it('is the reverse of the other direction, also for the second of two equal times', () => {
      const twice = zonedDateTimeToIso('2027-10-31', '02:30', 'Europe/Brussels');
      const winter = twice.kind === 'twice' ? twice.winter : null;

      expect(winter && instantToZonedDateTime(winter.iso, 'Europe/Brussels')).toEqual({
        date: '2027-10-31',
        time: '02:30',
        offsetMinutes: 60,
      });
    });

    it('gives null for a zone that the browser does not know, and for a text that is no date', () => {
      expect(instantToZonedDateTime(start, 'Not/AZone')).toBeNull();
      expect(instantToZonedDateTime('nothing', 'Europe/Brussels')).toBeNull();
    });
  });

  describe('the same clock in two zones', () => {
    it('says whether an instant reads the same in two zones', () => {
      expect(readsTheSameInZones(start, 'Europe/Brussels', 'Europe/Brussels')).toBe(true);
      expect(readsTheSameInZones(start, 'Europe/Brussels', 'Europe/Paris')).toBe(true);
      expect(readsTheSameInZones(start, 'Europe/Brussels', 'America/New_York')).toBe(false);
      expect(readsTheSameInZones('2026-11-18T16:00:00.000000Z', 'Europe/Brussels', 'Europe/London')).toBe(false);
      // Lisbon and London share the clock; a zone that is unknown never does
      expect(readsTheSameInZones(start, 'Europe/Lisbon', 'Europe/London')).toBe(true);
      expect(readsTheSameInZones(start, 'Europe/Brussels', 'Not/AZone')).toBe(false);
    });
  });
});
