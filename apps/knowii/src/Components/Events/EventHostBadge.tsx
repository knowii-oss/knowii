import type { SpaceEvent } from '@knowii/common';
import { FaMicrophone } from 'react-icons/fa6';
import { Badge } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';

export interface EventHostBadgeProps {
  event: Pick<SpaceEvent, 'host'>;
  /**
   * The cuid of the reader. Null: a visitor
   */
  readerCuid: string | null;
}

/**
 * "You host" (#1515): the label of an event that the reader hosts, on a row of "My events". Text, never a color alone.
 * Nothing for an event of another host, and for an event without a host (a deleted account).
 */
export function EventHostBadge({ event, readerCuid }: EventHostBadgeProps) {
  if (readerCuid === null || event.host?.cuid !== readerCuid) {
    return null;
  }

  return (
    <Badge variant="info" icon={<FaMicrophone />}>
      {EVENT_WORDS.youHost}
    </Badge>
  );
}
