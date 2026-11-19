import { useEffect, useState } from 'react';
import type { DateInput, SpaceEvent } from '@knowii/common';
import { toDate } from '@knowii/common';

/**
 * "Happening now" (design 6.1 point 3): a published event whose start is reached and whose end is not. A draft and a
 * cancelled event never happen.
 */
export function isHappeningNow(event: Pick<SpaceEvent, 'state'> & { starts_at: DateInput; ends_at: DateInput }, now: Date): boolean {
  return (
    event.state === 'published' && toDate(event.starts_at).getTime() <= now.getTime() && now.getTime() < toDate(event.ends_at).getTime()
  );
}

/**
 * Whether the start of an event is still to come: only then may a published event go back to draft
 */
export function startsInTheFuture(event: { starts_at: DateInput }, now: Date): boolean {
  return toDate(event.starts_at).getTime() > now.getTime();
}

/**
 * The order of the list "Upcoming" (design 6.1 points 3 and 5): the drafts first, then the next start first.
 * WARNING: aligned with Event::scopeUpcoming
 */
export function compareUpcoming(left: SpaceEvent, right: SpaceEvent): number {
  const drafts = Number(right.state === 'draft') - Number(left.state === 'draft');
  return drafts !== 0 ? drafts : toDate(left.starts_at).getTime() - toDate(right.starts_at).getTime();
}

/**
 * The order of the list "Past": the newest start first. WARNING: aligned with Event::scopePast
 */
export function comparePast(left: SpaceEvent, right: SpaceEvent): number {
  return toDate(right.starts_at).getTime() - toDate(left.starts_at).getTime();
}

/**
 * The present, renewed each minute: "Happening now" shows and goes without a reload of the page
 */
export function useNow(intervalMs = 60_000): Date {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), intervalMs);
    return () => clearInterval(timer);
  }, [intervalMs]);
  return now;
}
