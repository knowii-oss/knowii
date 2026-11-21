import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import type { SpaceEvent } from '@knowii/common';
import { Card, CoverImage } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';
import { EventAnswerBadges } from './EventAnswerBadges';
import { EventHostBadge } from './EventHostBadge';
import { EventStateBadges } from './EventStateBadges';
import { EventTime } from './EventTime';

export interface EventCardProps {
  event: SpaceEvent;
  /**
   * The address of the page of the event
   */
  href: string;
  now: Date;
  /**
   * One line of plain text above the title: where the event is ("Example community, Office hours" on a row of "My
   * events", #1515). None in the list of a space
   */
  context?: string;
  /**
   * The actions of the card, under the host line ("Add to calendar" on a row of "My events", #1515). None in the list of
   * a space: a member answers on the page of the event
   */
  actions?: ReactNode;
  /**
   * False: the card shows no cover (a row of "My events", #1515). Default true
   */
  cover?: boolean;
  /**
   * The cuid of the reader: the card then says "You host" on an event that the reader hosts (#1515). Not given in the
   * list of a space
   */
  readerCuid?: string | null;
}

/**
 * An event in the list of its space (#1514, design 6.1 point 4): the cover in a 16:9 box, the title as a heading that
 * links to the event, the time in the zone of the reader, and the host. On a narrow list the cover is above the title;
 * on a wide one it is next to it. The states are text labels, and so are the answer of the reader and the places that
 * are left (#1515). A card has no answer control: a member answers on the page of the event.
 *
 * A row of "My events" (#1515) uses the same card without the cover, with a line that names the space (and the
 * community), the label "You host", and "Add to calendar" under the host line.
 */
export function EventCard({ event, href, now, context, actions, cover = true, readerCuid }: EventCardProps) {
  const headingId = `event-${event.cuid}-title`;

  return (
    <Card as="article" aria-labelledby={headingId} className="flex flex-col gap-4 p-4 @2xl:flex-row">
      {/* An empty description marks a decoration: screen readers skip the picture */}
      {cover && <CoverImage src={event.cover?.url ?? null} alt={event.cover_alt ?? ''} className="shrink-0 @2xl:w-64" />}
      <div className="flex min-w-0 flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2 empty:hidden">
          <EventStateBadges event={event} now={now} />
          {readerCuid !== undefined && <EventHostBadge event={event} readerCuid={readerCuid} />}
          <EventAnswerBadges event={event} now={now} />
        </div>
        {context !== undefined && <p className="text-sm text-muted wrap-anywhere">{context}</p>}
        <h2 id={headingId} className="text-lg font-extrabold wrap-anywhere">
          <Link
            href={href}
            data-event-link={event.cuid}
            className="touch-target rounded hover:underline focus-visible:outline-2 focus-visible:outline-primary"
          >
            {event.title}
          </Link>
        </h2>
        <p className="text-sm">
          <EventTime start={event.starts_at} end={event.ends_at} form="short" />
        </p>
        <p className="text-sm text-muted">{EVENT_WORDS.hostedBy(event.host ? event.host.name : EVENT_WORDS.deletedMember)}</p>
        {/* An action that renders nothing (a draft has no calendar file) leaves no empty row */}
        {actions !== undefined && <div className="flex flex-wrap gap-2 empty:hidden">{actions}</div>}
      </div>
    </Card>
  );
}
