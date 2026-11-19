/**
 * The time zone of the account and of the browser (#1476, ADR 0048, design 6.7 and 9.9). A zone is an IANA name like
 * `Europe/Brussels`, or `UTC`.
 */

/**
 * Whether the browser knows the zone
 */
export function isKnownTimeZone(zone: string | null | undefined): zone is string {
  if (!zone) {
    return false;
  }
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: zone });
    return true;
  } catch {
    return false;
  }
}

/**
 * The older names of zones that browser engines still give (#1678), with the current name of the time zone database.
 * The server stores the current name only (`TimeZones::current`), so the screens use it too: one zone has one name. The
 * list holds every name of `Intl.supportedValuesOf('timeZone')` of V8 that is not a current name (checked on 2026-11-19
 * against the names that PHP lists).
 */
const CURRENT_TIME_ZONE_NAMES: Readonly<Record<string, string>> = {
  'Africa/Asmera': 'Africa/Asmara',
  'America/Buenos_Aires': 'America/Argentina/Buenos_Aires',
  'America/Catamarca': 'America/Argentina/Catamarca',
  'America/Coral_Harbour': 'America/Atikokan',
  'America/Cordoba': 'America/Argentina/Cordoba',
  'America/Godthab': 'America/Nuuk',
  'America/Indianapolis': 'America/Indiana/Indianapolis',
  'America/Jujuy': 'America/Argentina/Jujuy',
  'America/Louisville': 'America/Kentucky/Louisville',
  'America/Mendoza': 'America/Argentina/Mendoza',
  'Asia/Calcutta': 'Asia/Kolkata',
  'Asia/Katmandu': 'Asia/Kathmandu',
  'Asia/Rangoon': 'Asia/Yangon',
  'Asia/Saigon': 'Asia/Ho_Chi_Minh',
  'Atlantic/Faeroe': 'Atlantic/Faroe',
  'Europe/Kiev': 'Europe/Kyiv',
  'Pacific/Enderbury': 'Pacific/Kanton',
  'Pacific/Ponape': 'Pacific/Pohnpei',
  'Pacific/Truk': 'Pacific/Chuuk',
};

/**
 * The current name of a zone: `Asia/Kolkata` for `Asia/Calcutta`. Another name comes back as it is. When the browser
 * does not know the current name yet (an old browser), the older name stays: the server accepts it.
 */
export function currentTimeZoneName(zone: string): string {
  const current = Object.hasOwn(CURRENT_TIME_ZONE_NAMES, zone) ? CURRENT_TIME_ZONE_NAMES[zone] : undefined;
  return current !== undefined && isKnownTimeZone(current) ? current : zone;
}

/**
 * The zone that the browser reports, under its current name, or null when it gives none (nothing fails then)
 */
export function browserTimeZone(): string | null {
  try {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    return isKnownTimeZone(zone) ? currentTimeZoneName(zone) : null;
  } catch {
    return null;
  }
}

/**
 * Every zone that the browser knows, with `UTC`, each under its current name, sorted. Without `Intl.supportedValuesOf`
 * it is the zone of the browser and UTC.
 */
export function knownTimeZones(extra: readonly (string | null | undefined)[] = []): string[] {
  const zones = new Set<string>(['UTC']);
  const supported = (Intl as unknown as { supportedValuesOf?: (key: string) => string[] }).supportedValuesOf;
  if (typeof supported === 'function') {
    try {
      supported.call(Intl, 'timeZone').forEach((zone) => zones.add(currentTimeZoneName(zone)));
    } catch {
      // The browser does not know the key: the fallback below applies
    }
  }
  const browser = browserTimeZone();
  if (browser) {
    zones.add(browser);
  }
  extra.forEach((zone) => {
    if (zone) {
      zones.add(currentTimeZoneName(zone));
    }
  });
  return [...zones].sort((left, right) => left.localeCompare(right));
}

/**
 * A zone in words: "Central European Standard Time (Europe/Brussels)", or the name alone when the browser has no long name
 */
export function timeZoneInWords(zone: string, at: Date = new Date()): string {
  try {
    const part = new Intl.DateTimeFormat('en-US', { timeZone: zone, timeZoneName: 'long' })
      .formatToParts(at)
      .find((item) => item.type === 'timeZoneName');
    return part && part.value !== zone ? `${part.value} (${zone})` : zone;
  } catch {
    return zone;
  }
}

/**
 * A zone as an entry of a list to choose from (#1666)
 */
export interface TimeZoneOption {
  /**
   * The identifier of the zone, the value that the server gets: "Europe/Brussels"
   */
  value: string;
  /**
   * The place and the offset: "Brussels, Europe (UTC+1)"
   */
  label: string;
  /**
   * The long name of the zone, when the browser has one: "Central European Standard Time"
   */
  description?: string;
  /**
   * The identifier, so that a person can type it: "Europe/Brussels"
   */
  keywords: readonly string[];
}

/**
 * An offset in words: 60 gives "UTC+1", -300 gives "UTC-5", 330 gives "UTC+5:30", 0 gives "UTC+0"
 */
function utcOffsetInWords(minutes: number): string {
  const size = Math.abs(minutes);
  const rest = size % 60;
  return `UTC${minutes < 0 ? '-' : '+'}${Math.floor(size / 60)}${rest === 0 ? '' : `:${String(rest).padStart(2, '0')}`}`;
}

/**
 * One zone as an entry: the city (underscores as spaces), the region and the offset at `at` in the label, the long name
 * as the description. "America/Argentina/Buenos_Aires" gives "Buenos Aires, Argentina, America (UTC-3)". A zone that
 * the browser does not know keeps its place alone, without an offset.
 */
function timeZoneOption(zone: string, at: Date): TimeZoneOption {
  const place = zone
    .split('/')
    .map((part) => part.replaceAll('_', ' '))
    .reverse()
    .join(', ');
  // The identifier, and the older names of the zone: a person who types "calcutta" finds Kolkata
  const keywords = [zone, ...Object.keys(CURRENT_TIME_ZONE_NAMES).filter((older) => CURRENT_TIME_ZONE_NAMES[older] === zone)];
  try {
    // The date and the hour of the zone, read as if they were UTC, minus the real instant: the offset of the zone
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: zone,
      hourCycle: 'h23',
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      second: 'numeric',
      timeZoneName: 'long',
    }).formatToParts(at);
    const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
    const number = (type: Intl.DateTimeFormatPartTypes) => Number(part(type));
    const wallClock = Date.UTC(number('year'), number('month') - 1, number('day'), number('hour'), number('minute'), number('second'));
    const offset = utcOffsetInWords(Math.round((wallClock - at.getTime()) / 60_000));
    const longName = part('timeZoneName');
    // Without a name, Intl gives the offset again ("GMT+03:00"): that is no description
    const hasLongName = longName !== '' && longName !== zone && !/^(GMT|UTC)([+-]|$)/.test(longName);
    return {
      value: zone,
      // "UTC (UTC+0)" says the same thing twice
      label: zone === 'UTC' ? place : `${place} (${offset})`,
      ...(hasLongName ? { description: longName } : {}),
      keywords,
    };
  } catch {
    return { value: zone, label: place, keywords };
  }
}

/**
 * Every zone that the browser knows (`knownTimeZones`, in the same order) as the entries of a list to choose from
 * (#1666): "Brussels, Europe (UTC+1)" with the long name of the zone, and the identifier as a keyword for a search. The
 * offset is the one at `at`: it changes with the daylight saving time.
 */
export function timeZoneOptions(extra: readonly (string | null | undefined)[] = [], at: Date = new Date()): TimeZoneOption[] {
  return knownTimeZones(extra).map((zone) => timeZoneOption(zone, at));
}

/**
 * A date of the server: a `Date` (an answer of the API, parsed by Zod) or an ISO 8601 text (an Inertia prop is not
 * parsed: its dates are texts, rules.md Front-end 6)
 */
export type DateInput = Date | string;

export function toDate(value: DateInput): Date {
  return value instanceof Date ? value : new Date(value);
}

/**
 * What a formatter gives when `Intl` fails: the ISO text of the instant, or nothing for a value that is no date
 */
function isoOrNothing(date: Date): string {
  return Number.isNaN(date.getTime()) ? '' : date.toISOString();
}

/**
 * The start of an event in a short line (#1513): "Tue 18 Nov, 17:00 Central European Standard Time", in the zone of the
 * account when there is one (else in the zone of the browser). The weekday comes from the date, and the zone in words is
 * what `Intl` gives with `timeZoneName: 'long'`. The year follows the month when it is not the current year of that
 * zone, like in the long form: "Thu 14 Jan 2027, 23:00 Central European Standard Time". `now` is the present (the tests
 * give it).
 */
export function formatEventStart(value: DateInput, zone: string | null | undefined, now: Date = new Date()): string {
  const date = toDate(value);
  const timeZone = isKnownTimeZone(zone) ? zone : undefined;
  try {
    const format = new Intl.DateTimeFormat('en-GB', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
      timeZoneName: 'long',
      ...(timeZone ? { timeZone } : {}),
    });
    const parts = format.formatToParts(date);
    const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
    const currentYear = format.formatToParts(now).find((item) => item.type === 'year')?.value;
    const year = part('year') === currentYear ? '' : ` ${part('year')}`;
    return `${part('weekday')} ${part('day')} ${part('month')}${year}, ${part('hour')}:${part('minute')} ${part('timeZoneName')}`;
  } catch {
    return isoOrNothing(date);
  }
}

/**
 * A date and an hour in full, in the zone of the account when there is one (else in the zone of the browser), with the name
 * of the zone: "Nov 15, 2026, 10:00:00 AM CET"
 */
export function formatFullDateTime(date: Date, zone: string | null | undefined): string {
  const timeZone = isKnownTimeZone(zone) ? zone : undefined;
  try {
    return new Intl.DateTimeFormat('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      second: '2-digit',
      timeZoneName: 'short',
      ...(timeZone ? { timeZone } : {}),
    }).format(date);
  } catch {
    return date.toISOString();
  }
}

/**
 * The times of events (#1514, ADR 0053, design 4 and 11.2). `Intl` only, the locale `en-GB`, 24 hours, and the zone in
 * the words of `Intl` with `timeZoneName: 'long'` ("Central European Standard Time"). The weekday always comes from the
 * date in the zone: the same instant is a Wednesday in Brussels and a Thursday in Singapore.
 */

/**
 * The date and the hour that a clock shows in a zone
 */
interface WallClock {
  year: number;
  month: number;
  day: number;
  hour: number;
  minute: number;
}

const wallClockFormats = new Map<string, Intl.DateTimeFormat>();

/**
 * What a clock shows in the zone at an instant. Throws for a zone that the browser does not know.
 */
function wallClockIn(zone: string, at: Date): WallClock {
  let format = wallClockFormats.get(zone);
  if (!format) {
    format = new Intl.DateTimeFormat('en-US', {
      timeZone: zone,
      hourCycle: 'h23',
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
    });
    wallClockFormats.set(zone, format);
  }
  const parts = format.formatToParts(at);
  const number = (type: Intl.DateTimeFormatPartTypes) => Number(parts.find((item) => item.type === type)?.value ?? '0');
  return { year: number('year'), month: number('month'), day: number('day'), hour: number('hour'), minute: number('minute') };
}

/**
 * A wall clock as milliseconds, read as if it were UTC: for arithmetic on local dates and hours
 */
function wallClockMs(clock: WallClock): number {
  return Date.UTC(clock.year, clock.month - 1, clock.day, clock.hour, clock.minute);
}

/**
 * The offset of the zone at an instant, in minutes: 60 in Brussels in the winter, 120 in the summer, -300 in New York
 */
function zoneOffsetMinutes(zone: string, at: Date): number {
  const wholeMinute = Math.floor(at.getTime() / 60_000) * 60_000;
  return Math.round((wallClockMs(wallClockIn(zone, new Date(wholeMinute))) - wholeMinute) / 60_000);
}

const two = (value: number) => String(value).padStart(2, '0');

/**
 * "+01:00", "-05:00", "+05:30": the offset of an ISO 8601 text
 */
function isoOffset(minutes: number): string {
  const size = Math.abs(minutes);
  return `${minutes < 0 ? '-' : '+'}${two(Math.floor(size / 60))}:${two(size % 60)}`;
}

/**
 * The offset of a zone at an instant, in words: "UTC+1" for Brussels in the winter, "UTC+2" in the summer, "UTC-5",
 * "UTC+5:30". An empty text for a zone that the browser does not know.
 */
export function utcOffsetLabel(at: DateInput, zone: string): string {
  try {
    return utcOffsetInWords(zoneOffsetMinutes(zone, toDate(at)));
  } catch {
    return '';
  }
}

/**
 * A local date and time of a zone as an instant, or why it is none (design 4.2 points 4 and 5)
 */
export type ZonedDateTime =
  // One instant: the ISO 8601 text with the offset of the zone, as the API takes it ("2026-11-18T17:00:00+01:00")
  | { kind: 'instant'; iso: string; offsetMinutes: number }
  // The clocks go forward over this time: it does not exist. `before` is the last minute before the gap ("01:59"),
  // `after` the first minute after it ("03:00"). `beforeIsDayBefore`: the gap starts at midnight, so the last minute
  // before it ("23:59") is on the day before the date
  | { kind: 'skipped'; before: string; after: string; beforeIsDayBefore: boolean }
  // The clocks go back over this time: it exists twice. The larger offset first (the summer time), then the winter time
  | { kind: 'twice'; summer: { iso: string; offsetMinutes: number }; winter: { iso: string; offsetMinutes: number } }
  // No date, no time, or a zone that the browser does not know
  | { kind: 'invalid' };

/**
 * The instant of a local date ("2026-11-18") and time ("17:00") in a zone, as ISO 8601 with the offset of the zone at
 * that time: "2026-11-18T17:00:00+01:00". A time that the zone skips (28 March 2027, 02:30 in Brussels) gives `skipped`
 * with the two times around the gap (and whether the first one is on the day before: 28 March 2027, 00:30 in the
 * Azores); a time that exists twice (31 October 2027, 02:30 in Brussels) gives `twice` with
 * both instants, and the person chooses.
 */
export function zonedDateTimeToIso(date: string, time: string, zone: string): ZonedDateTime {
  const dateParts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
  const timeParts = /^(\d{2}):(\d{2})$/.exec(time);
  if (!dateParts || !timeParts || !isKnownTimeZone(zone)) {
    return { kind: 'invalid' };
  }
  const clock: WallClock = {
    year: Number(dateParts[1]),
    month: Number(dateParts[2]),
    day: Number(dateParts[3]),
    hour: Number(timeParts[1]),
    minute: Number(timeParts[2]),
  };
  const wall = wallClockMs(clock);
  // A date that no calendar has (31 November) is moved by Date.UTC: refuse it
  const check = new Date(wall);
  if (
    check.getUTCFullYear() !== clock.year ||
    check.getUTCMonth() !== clock.month - 1 ||
    check.getUTCDate() !== clock.day ||
    clock.hour > 23 ||
    clock.minute > 59
  ) {
    return { kind: 'invalid' };
  }

  const day = 24 * 60 * 60_000;
  // The offsets of the zone a day before and a day after: the two sides of a change of the clocks near this time
  const offsets = [...new Set([zoneOffsetMinutes(zone, new Date(wall - day)), zoneOffsetMinutes(zone, new Date(wall + day))])];
  // An offset is right when its instant, read in the zone, shows the same clock
  const matches = offsets
    .filter((offset) => wallClockMs(wallClockIn(zone, new Date(wall - offset * 60_000))) === wall)
    .sort((left, right) => right - left)
    .map((offset) => ({ iso: `${date}T${time}:00${isoOffset(offset)}`, offsetMinutes: offset }));

  const [first, second] = matches;
  if (first && second) {
    return { kind: 'twice', summer: first, winter: second };
  }
  if (first) {
    return { kind: 'instant', ...first };
  }

  // No instant shows this clock: the clocks went forward over it. The change is the first minute with the new offset
  const before = Math.min(...offsets);
  const after = Math.max(...offsets);
  if (before === after) {
    return { kind: 'invalid' };
  }
  let low = wall - after * 60_000;
  let high = wall - before * 60_000;
  while (high - low > 60_000) {
    const middle = low + Math.floor((high - low) / 120_000) * 60_000;
    if (zoneOffsetMinutes(zone, new Date(middle)) === after) {
      high = middle;
    } else {
      low = middle;
    }
  }
  const lastBefore = new Date(high + before * 60_000 - 60_000);
  const firstAfter = new Date(high + after * 60_000);
  return {
    kind: 'skipped',
    before: `${two(lastBefore.getUTCHours())}:${two(lastBefore.getUTCMinutes())}`,
    after: `${two(firstAfter.getUTCHours())}:${two(firstAfter.getUTCMinutes())}`,
    // Both are wall clocks read as UTC, like `wall`
    beforeIsDayBefore: lastBefore.getUTCDate() !== check.getUTCDate(),
  };
}

/**
 * The reverse of `zonedDateTimeToIso`, to fill the form of an event that exists: the local date ("2026-11-18"), the
 * local time ("17:00") and the offset of the zone at that instant. Null for a zone that the browser does not know.
 */
export function instantToZonedDateTime(at: DateInput, zone: string): { date: string; time: string; offsetMinutes: number } | null {
  if (!isKnownTimeZone(zone)) {
    return null;
  }
  const instant = toDate(at);
  if (Number.isNaN(instant.getTime())) {
    return null;
  }
  const clock = wallClockIn(zone, instant);
  return {
    date: `${String(clock.year).padStart(4, '0')}-${two(clock.month)}-${two(clock.day)}`,
    time: `${two(clock.hour)}:${two(clock.minute)}`,
    offsetMinutes: zoneOffsetMinutes(zone, instant),
  };
}

/**
 * Whether an instant shows the same date and hour on the clocks of two zones (Brussels and Paris, always; Brussels and
 * London, never). The event page adds "The host's time" when it does not, and the form says "the same" when it does.
 */
export function readsTheSameInZones(at: DateInput, zone: string, otherZone: string): boolean {
  if (zone === otherZone) {
    return true;
  }
  try {
    const instant = toDate(at);
    return wallClockMs(wallClockIn(zone, instant)) === wallClockMs(wallClockIn(otherZone, instant));
  } catch {
    return false;
  }
}

/**
 * The words of a date and an hour in a zone
 */
interface LongParts {
  weekday: string;
  day: string;
  month: string;
  year: string;
  time: string;
  zoneName: string;
}

function longParts(at: Date, timeZone: string | undefined): LongParts {
  const parts = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZoneName: 'long',
    ...(timeZone ? { timeZone } : {}),
  }).formatToParts(at);
  const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
  return {
    weekday: part('weekday'),
    day: part('day'),
    month: part('month'),
    year: part('year'),
    time: `${part('hour')}:${part('minute')}`,
    zoneName: part('timeZoneName'),
  };
}

export interface EventTimeFormatOptions {
  /**
   * `auto` (the default): the year follows the month only when it is not the current year. `always`: the form of the
   * host, where the year is part of the check.
   */
  year?: 'auto' | 'always';
  /**
   * Adds the offset after the zone, after a comma: ", Central European Standard Time (UTC+1)". For the form of the host.
   */
  offset?: boolean;
  /**
   * The present, for the current year (the tests give it)
   */
  now?: Date;
}

/**
 * The start and the end of an event in full (design 4.3): "Wednesday 18 November, 11:00 to 12:00 Eastern Standard
 * Time", in the given zone, else in the zone of the browser. An end on another day names that day too: "Wednesday 18
 * November, 23:00 to Thursday 19 November, 01:00 Central European Standard Time". The year follows the month when it
 * is not the current year. When the clocks change between the start and the end, each time has its own zone words.
 * Without an end, the start alone: "Wednesday 18 November, 17:00 Central European Standard Time".
 */
export function formatEventRange(
  start: DateInput,
  end: DateInput | null,
  zone: string | null | undefined,
  options: EventTimeFormatOptions = {},
): string {
  const startDate = toDate(start);
  const timeZone = isKnownTimeZone(zone) ? zone : undefined;
  try {
    const currentYear = longParts(options.now ?? new Date(), timeZone).year;
    const dateOf = (parts: LongParts) =>
      `${parts.weekday} ${parts.day} ${parts.month}${options.year === 'always' || parts.year !== currentYear ? ` ${parts.year}` : ''}`;
    const zoneOf = (parts: LongParts, at: Date) => {
      if (!options.offset) {
        return ` ${parts.zoneName}`;
      }
      const offset = utcOffsetLabel(at, timeZone ?? browserTimeZone() ?? 'UTC');
      return `, ${parts.zoneName}${offset === '' ? '' : ` (${offset})`}`;
    };

    const from = longParts(startDate, timeZone);
    if (end === null) {
      return `${dateOf(from)}, ${from.time}${zoneOf(from, startDate)}`;
    }
    const endDate = toDate(end);
    const to = longParts(endDate, timeZone);
    const sameDay = from.day === to.day && from.month === to.month && from.year === to.year;
    const until = `${sameDay ? '' : `${dateOf(to)}, `}${to.time}`;
    return from.zoneName === to.zoneName
      ? `${dateOf(from)}, ${from.time} to ${until}${zoneOf(to, endDate)}`
      : `${dateOf(from)}, ${from.time}${zoneOf(from, startDate)} to ${until}${zoneOf(to, endDate)}`;
  } catch {
    return isoOrNothing(startDate);
  }
}
