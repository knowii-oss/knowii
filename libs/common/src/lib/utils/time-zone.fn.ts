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
 * The zone that the browser reports, or null when it gives none (nothing fails then)
 */
export function browserTimeZone(): string | null {
  try {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    return isKnownTimeZone(zone) ? zone : null;
  } catch {
    return null;
  }
}

/**
 * Every zone that the browser knows, with `UTC`, sorted. Without `Intl.supportedValuesOf` it is the zone of the browser and UTC.
 */
export function knownTimeZones(extra: readonly (string | null | undefined)[] = []): string[] {
  const zones = new Set<string>(['UTC']);
  const supported = (Intl as unknown as { supportedValuesOf?: (key: string) => string[] }).supportedValuesOf;
  if (typeof supported === 'function') {
    try {
      supported.call(Intl, 'timeZone').forEach((zone) => zones.add(zone));
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
      zones.add(zone);
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
