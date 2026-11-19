import type { DateInput } from '@knowii/common';
import { browserTimeZone, readsTheSameInZones } from '@knowii/common';
import { useAccountTimeZone } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';
import { EventTime } from './EventTime';

export interface EventWhenProps {
  start: DateInput;
  end: DateInput;
  /**
   * The zone in which the host entered the time
   */
  timeZone: string;
}

/**
 * The date block of the page of an event (#1514, design 4.3 and 6.2 point 3), as text: the weekday, the date, the
 * hours and the zone in words, in the zone of the reader (the account, else the browser). When the start reads
 * differently on the clock of the host, a second line gives the time of the host. Both are `time` elements with the
 * UTC start. Never a picture of a calendar page.
 */
export function EventWhen({ start, end, timeZone }: EventWhenProps) {
  const accountZone = useAccountTimeZone();
  const readerZone = accountZone ?? browserTimeZone() ?? 'UTC';
  const hostReadsAnotherTime = !readsTheSameInZones(start, timeZone, readerZone);

  return (
    <section aria-labelledby="event-when-title" className="flex flex-col gap-1">
      <h2 id="event-when-title" className="sr-only">
        {EVENT_WORDS.when}
      </h2>
      <p className="text-lg font-bold">
        <EventTime start={start} end={end} form="long" />
      </p>
      {hostReadsAnotherTime && (
        <p className="text-sm text-muted">
          {EVENT_WORDS.hostTimeLabel} <EventTime start={start} end={null} form="long" timeZone={timeZone} />
        </p>
      )}
    </section>
  );
}
