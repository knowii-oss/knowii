import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  browserTimeZone,
  currentTimeZoneName,
  formatEventStart,
  formatFullDateTime,
  isKnownTimeZone,
  knownTimeZones,
  timeZoneInWords,
  timeZoneOptions,
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

    expect(formatEventStart(date, 'Europe/Brussels')).toBe(`${weekday('Europe/Brussels')} 18 Nov, 17:00 ${zoneName('Europe/Brussels')}`);
    expect(formatEventStart(date, 'America/New_York')).toBe(`${weekday('America/New_York')} 18 Nov, 11:00 ${zoneName('America/New_York')}`);
    expect(formatEventStart(date, 'Asia/Singapore')).toBe(`${weekday('Asia/Singapore')} 19 Nov, 00:00 ${zoneName('Asia/Singapore')}`);
    expect(weekday('Asia/Singapore')).not.toBe(weekday('Europe/Brussels'));
    expect(formatEventStart(date, 'Europe/Brussels')).toMatch(/Central European/);
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
