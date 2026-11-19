import type { DateInput } from '@knowii/common';
import { formatEventRange, formatEventStart, toDate } from '@knowii/common';
import { useAccountTimeZone } from '@/Components/ui';

export interface EventTimeProps {
  start: DateInput;
  /**
   * Null: the start alone ("Wednesday 18 November, 17:00 Central European Standard Time")
   */
  end: DateInput | null;
  /**
   * `long` (a page): "Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time". `short` (a list): "Wed 18 Nov,
   * 11:00 Eastern Standard Time" for the eyes, and the long form for a screen reader. Both name the year when it is
   * not the current year.
   */
  form: 'long' | 'short';
  /**
   * The zone of the text, for the time of the host of an event. Default: the zone of the reader (the account, else the
   * browser).
   */
  timeZone?: string;
  className?: string;
}

/**
 * The time of an event (#1514, design 4.3 and 11.2): a `time` element with the UTC start in `datetime`, and the text in
 * the zone of the account, else in the zone of the browser, with the zone in words. Never a tooltip, never a relative
 * time: the weekday and the date are always written. With `timeZone`, the text is in that zone: the time of the host.
 */
export function EventTime({ start, end, form, timeZone, className }: EventTimeProps) {
  const accountZone = useAccountTimeZone();
  const zone = timeZone ?? accountZone;
  const startDate = toDate(start);
  const dateTime = Number.isNaN(startDate.getTime()) ? undefined : startDate.toISOString();
  const long = formatEventRange(start, end, zone);

  return (
    <time dateTime={dateTime} className={className}>
      {form === 'long' ? (
        long
      ) : (
        <>
          <span aria-hidden="true">{formatEventStart(start, zone)}</span>
          <span className="sr-only">{long}</span>
        </>
      )}
    </time>
  );
}
