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
 * The start of an event in a short line (#1513): "Tue 18 Nov, 17:00 Central European Standard Time", in the zone of the
 * account when there is one (else in the zone of the browser). The weekday comes from the date, and the zone in words is
 * what `Intl` gives with `timeZoneName: 'long'`.
 */
export function formatEventStart(date: Date, zone: string | null | undefined): string {
  const timeZone = isKnownTimeZone(zone) ? zone : undefined;
  try {
    const parts = new Intl.DateTimeFormat('en-GB', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
      timeZoneName: 'long',
      ...(timeZone ? { timeZone } : {}),
    }).formatToParts(date);
    const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
    return `${part('weekday')} ${part('day')} ${part('month')}, ${part('hour')}:${part('minute')} ${part('timeZoneName')}`;
  } catch {
    return date.toISOString();
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
