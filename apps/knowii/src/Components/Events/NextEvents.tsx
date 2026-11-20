import { Link } from '@inertiajs/react';
import type { Community, SpaceEvent } from '@knowii/common';
import { EVENT_URL } from '@knowii/common';
import { FaBan, FaRegCalendar } from 'react-icons/fa6';
import { Badge, Card, CardBody, CardHeader } from '@/Components/ui';
import { useNow } from './event-state';
import { EVENT_WORDS } from './event-words';
import { EventAnswerBadges } from './EventAnswerBadges';
import { EventTime } from './EventTime';

export interface NextEventsProps {
  community: Community;
  /**
   * The next upcoming events that the reader can read, the next start first: 3 at most, never a draft
   */
  events: readonly SpaceEvent[];
  /**
   * Names the space of each event: on the home of a member. The public page shows the title and the time only.
   */
  showSpace?: boolean;
}

/**
 * "Next events" on the home and on the public page of a community (#1514, design 6.7 points 1 and 3): up to 3 lines,
 * each with the title as a link to the event and the time in the zone of the reader. A cancelled event stays in the
 * list with its label, so that a member who planned to come reads the news. A member also reads their answer and the
 * places that are left, as labels (#1515); a visitor gets none. No event, no block. The list is the one of the page
 * load: it does not change live.
 */
export function NextEvents({ community, events, showSpace = false }: NextEventsProps) {
  const now = useNow();
  // An event without its space cannot be linked: the server always gives it here
  const lines = events.filter((event) => event.space !== undefined);
  if (lines.length === 0) {
    return null;
  }

  return (
    <Card aria-labelledby="next-events-title" className="overflow-hidden">
      <CardHeader title={<span id="next-events-title">{EVENT_WORDS.nextEvents}</span>} icon={<FaRegCalendar aria-hidden="true" />} />
      <CardBody>
        <ul className="flex flex-col gap-4">
          {lines.map((event) => (
            <li key={event.cuid} className="flex flex-col gap-1">
              <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <Link
                  href={route(EVENT_URL, { communitySlug: community.slug, spaceSlug: event.space?.slug ?? '', eventSlug: event.slug })}
                  className="touch-target rounded font-bold wrap-anywhere hover:underline focus-visible:outline-2 focus-visible:outline-primary"
                >
                  {event.title}
                </Link>
                {event.state === 'cancelled' && (
                  <Badge variant="danger" icon={<FaBan />}>
                    {EVENT_WORDS.cancelled}
                  </Badge>
                )}
                <EventAnswerBadges event={event} now={now} />
              </p>
              <p className="text-sm">
                <EventTime start={event.starts_at} end={event.ends_at} form="short" />
              </p>
              {showSpace && event.space && <p className="text-xs text-muted">{event.space.name}</p>}
            </li>
          ))}
        </ul>
      </CardBody>
    </Card>
  );
}
