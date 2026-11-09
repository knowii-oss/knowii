import { format, formatDistanceToNow } from 'date-fns';

export interface RelativeTimeProps {
  /**
   * A Date, or an ISO 8601 string: Inertia props are not parsed, so their dates are strings
   */
  date: Date | string;
  className?: string;
}

/**
 * "3 hours ago", with the exact date and time in the tooltip and in the machine-readable datetime attribute.
 */
export function RelativeTime({ date: input, className }: RelativeTimeProps) {
  const date = input instanceof Date ? input : new Date(input);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return (
    <time dateTime={date.toISOString()} title={format(date, 'PPpp')} className={className}>
      {formatDistanceToNow(date, { addSuffix: true })}
    </time>
  );
}
