import { formatDistanceToNow } from 'date-fns';
import { formatFullDateTime } from '@knowii/common';
import { useAccountTimeZone } from './AccountTimeZone';

export interface RelativeTimeProps {
  /**
   * A Date, or an ISO 8601 string: Inertia props are not parsed, so their dates are strings
   */
  date: Date | string;
  className?: string;
}

/**
 * "3 hours ago", with the exact date and time in the tooltip and in the machine-readable datetime attribute. The full date
 * and hour are in the time zone of the account when it has one, and the zone is named (#1476, design 9.9). The relative
 * text does not change.
 */
export function RelativeTime({ date: input, className }: RelativeTimeProps) {
  const zone = useAccountTimeZone();
  const date = input instanceof Date ? input : new Date(input);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return (
    <time dateTime={date.toISOString()} title={formatFullDateTime(date, zone)} className={className}>
      {formatDistanceToNow(date, { addSuffix: true })}
    </time>
  );
}
