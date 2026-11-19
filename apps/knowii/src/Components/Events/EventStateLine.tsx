import type { DateInput, SpaceEvent } from '@knowii/common';
import { FaBan, FaCircle, FaPenRuler } from 'react-icons/fa6';
import { EVENT_WORDS } from './event-words';
import { isHappeningNow } from './event-state';

export interface EventStateLineProps {
  event: Pick<SpaceEvent, 'state' | 'is_past'> & { starts_at: DateInput; ends_at: DateInput };
  now: Date;
}

/**
 * The state of an event on its page, as text (#1514, design 6.2 point 4 and 11.2): who sees a draft, the banner
 * "Cancelled", "Happening now", and "This event is over". Nothing for a published event that is still to come. Never a
 * color alone: each line has its words, and an icon that a screen reader skips.
 */
export function EventStateLine({ event, now }: EventStateLineProps) {
  const lines = [];
  if (event.state === 'draft') {
    lines.push(
      <p key="draft" className="flex items-start gap-2 rounded-lg border border-warning bg-overlay/5 px-3 py-2 text-sm font-bold">
        <FaPenRuler aria-hidden="true" className="mt-0.5 shrink-0 text-warning" />
        {EVENT_WORDS.draftNotice}
      </p>,
    );
  }
  if (event.state === 'cancelled') {
    lines.push(
      <p
        key="cancelled"
        className="flex items-center gap-2 rounded-lg border-2 border-danger-text bg-overlay/5 px-3 py-2 text-base font-extrabold"
      >
        <FaBan aria-hidden="true" className="shrink-0 text-danger-text" />
        {EVENT_WORDS.cancelled}
      </p>,
    );
  }
  if (isHappeningNow(event, now)) {
    lines.push(
      <p key="now" className="flex items-center gap-2 text-sm font-bold">
        <FaCircle aria-hidden="true" className="size-2.5 shrink-0 text-success" />
        {EVENT_WORDS.happeningNow}
      </p>,
    );
  }
  if (event.is_past) {
    lines.push(
      <p key="over" className="text-sm font-bold text-muted">
        {EVENT_WORDS.over}
      </p>,
    );
  }

  return lines.length === 0 ? null : (
    <div data-slot="event-state" className="flex flex-col gap-2">
      {lines}
    </div>
  );
}
