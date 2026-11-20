import type { DateInput, EventAnswerValue, SpaceEvent } from '@knowii/common';
import { toDate } from '@knowii/common';
import { FaCheck } from 'react-icons/fa6';
import type { BadgeVariant } from '@/Components/ui';
import { Badge } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';

export interface EventAnswerBadgesProps {
  event: Pick<SpaceEvent, 'state' | 'is_past' | 'my_answer' | 'places_left'> & { ends_at: DateInput };
  now: Date;
}

const ANSWER_VARIANTS: Record<EventAnswerValue, BadgeVariant> = { yes: 'success', replay: 'info', no: 'neutral' };

/**
 * The answer of the reader and the places of an event, as text labels on a card of the list and on a line of "Next
 * events" (#1515, design 6.1 point 4): "You said Yes", "28 places left", "Full". The answer shows on every event that
 * the member answered, also a cancelled or a past one. The places show only while the event takes answers (published
 * and not over), always in the neutral color: no urgency. A visitor and a person who is not a member get no label, because
 * the server gives them no answer and no places. No control: a member answers on the page of the event.
 */
export function EventAnswerBadges({ event, now }: EventAnswerBadgesProps) {
  const takesAnswers = event.state === 'published' && !event.is_past && toDate(event.ends_at).getTime() > now.getTime();

  return (
    <>
      {event.my_answer !== null && (
        <Badge variant={ANSWER_VARIANTS[event.my_answer]} {...(event.my_answer === 'yes' ? { icon: <FaCheck /> } : {})}>
          {EVENT_WORDS.youSaid(event.my_answer)}
        </Badge>
      )}
      {event.places_left !== null && takesAnswers && (
        <Badge variant="neutral">{event.places_left === 0 ? EVENT_WORDS.full : EVENT_WORDS.placesLeft(event.places_left)}</Badge>
      )}
    </>
  );
}
