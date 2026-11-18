import { afterEach, describe, expect, it, vi } from 'vitest';
import { browserTimeZone, formatEventStart, formatFullDateTime, isKnownTimeZone, knownTimeZones, timeZoneInWords } from './time-zone.fn';

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
