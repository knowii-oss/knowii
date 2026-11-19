import type { DateInput, SpaceEvent } from '@knowii/common';
import { FaBan, FaCircle, FaPenRuler } from 'react-icons/fa6';
import { Badge } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';
import { isHappeningNow } from './event-state';

export interface EventStateBadgesProps {
  event: Pick<SpaceEvent, 'state'> & { starts_at: DateInput; ends_at: DateInput };
  now: Date;
}

/**
 * The state of an event as text labels (design 11.2: never a color alone): "Draft", "Cancelled", "Happening now".
 * Nothing for a published event that is not in progress.
 */
export function EventStateBadges({ event, now }: EventStateBadgesProps) {
  if (event.state === 'draft') {
    return (
      <Badge variant="warning" icon={<FaPenRuler />}>
        {EVENT_WORDS.draft}
      </Badge>
    );
  }
  if (event.state === 'cancelled') {
    return (
      <Badge variant="danger" icon={<FaBan />}>
        {EVENT_WORDS.cancelled}
      </Badge>
    );
  }
  if (isHappeningNow(event, now)) {
    return (
      <Badge variant="success" icon={<FaCircle />}>
        {EVENT_WORDS.happeningNow}
      </Badge>
    );
  }
  return null;
}
