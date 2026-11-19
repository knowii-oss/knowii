import { Link } from '@inertiajs/react';
import type { SpaceEvent } from '@knowii/common';
import { Card, CoverImage } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';
import { EventStateBadges } from './EventStateBadges';
import { EventTime } from './EventTime';

export interface EventCardProps {
  event: SpaceEvent;
  /**
   * The address of the page of the event
   */
  href: string;
  now: Date;
}

/**
 * An event in the list of its space (#1514, design 6.1 point 4): the cover in a 16:9 box, the title as a heading that
 * links to the event, the time in the zone of the reader, and the host. On a narrow list the cover is above the title;
 * on a wide one it is next to it. The states are text labels.
 */
export function EventCard({ event, href, now }: EventCardProps) {
  const headingId = `event-${event.cuid}-title`;

  return (
    <Card as="article" aria-labelledby={headingId} className="flex flex-col gap-4 p-4 @2xl:flex-row">
      {/* An empty description marks a decoration: screen readers skip the picture */}
      <CoverImage src={event.cover?.url ?? null} alt={event.cover_alt ?? ''} className="shrink-0 @2xl:w-64" />
      <div className="flex min-w-0 flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2 empty:hidden">
          <EventStateBadges event={event} now={now} />
        </div>
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
      </div>
    </Card>
  );
}
