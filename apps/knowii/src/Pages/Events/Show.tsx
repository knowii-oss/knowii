import { useMemo } from 'react';
import { router } from '@inertiajs/react';
import type { Community, EventPermissions, Space, SpaceContentPayload, SpaceEvent, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, SPACE_TYPE_LABELS, SPACE_URL, useSocket, useTypedPage } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { EventActions } from '@/Components/Events/EventActions';
import { EventHost } from '@/Components/Events/EventHost';
import { EventMeeting } from '@/Components/Events/EventMeeting';
import { EventStateLine } from '@/Components/Events/EventStateLine';
import { EventWhen } from '@/Components/Events/EventWhen';
import { EVENT_WORDS } from '@/Components/Events/event-words';
import { useNow } from '@/Components/Events/event-state';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { CoverImage, ErrorBoundary, MarkdownContent } from '@/Components/ui';

interface Props {
  // WARNING: aligned with EventController::show
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  // With its description. The server cut the meeting address and the place for a reader who may not see them
  event: SpaceEvent;
  permissions: EventPermissions;
}

/**
 * The page of an event of an events space (#1514, ADR 0053, design 6.2). In this order: the title (the only h1), the
 * state as text, the cover, the date in the zone of the reader, the host, where it happens, and the description. A
 * visitor and a person who is not a member read it in a public space, without the meeting address: the server cuts it.
 *
 * Live (never for a visitor): when the space channel says that this event was published, changed or went away, the
 * page loads again, and the server decides what the reader then sees. The changes that the reader makes here load the page themselves.
 */
export default function EventPage({ community, space, spaceGroups, event, permissions }: Props) {
  const user = useTypedPage().props.auth.user;
  const now = useNow();
  const isMember = community.is_member === true;
  const spaceChannel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );

  useSocket({
    channel: spaceChannel,
    // A visitor gets a static page: no connection (#1387, ADR 0044 point 13)
    enabled: !!user,
    // `created` too: it says that a draft was published, and an admin can have the page of that draft open
    event: ['space.event.created', 'space.event.updated', 'space.event.deleted'],
    callback: (_name, payload) => {
      const { event_cuid: eventCuid, author_cuid: authorCuid } = payload as SpaceContentPayload;
      // The own changes of the reader already load the page, or leave it (a delete)
      if (eventCuid === event.cuid && authorCuid !== user?.cuid) {
        router.reload();
      }
    },
  });

  const spaceUrl = route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug });
  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    {
      label: space.name,
      href: spaceUrl,
      icon: <span aria-hidden="true">{space.emoji || SPACE_TYPE_LABELS[space.type].emoji}</span>,
    },
    { label: event.title },
  ];

  return (
    <AppLayout
      browserPageTitle={`${event.title} - ${space.name} - ${community.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      <CommunityShell community={community} spaceGroups={spaceGroups} activeSpaceCuid={space.cuid} contentWidth="readable">
        <article aria-labelledby="event-title" className="flex flex-col gap-6">
          <header className="flex flex-col gap-3">
            <h1 id="event-title" className="text-2xl font-extrabold wrap-anywhere sm:text-3xl">
              {event.title}
            </h1>
            <EventStateLine event={event} now={now} />
            <EventActions
              community={community}
              space={space}
              event={event}
              permissions={permissions}
              now={now}
              onChanged={() => router.reload()}
            />
          </header>

          {/* An empty description marks a decoration: screen readers skip the picture */}
          {event.cover && <CoverImage src={event.cover.url} alt={event.cover_alt ?? ''} loading="eager" />}

          <EventWhen start={event.starts_at} end={event.ends_at} timeZone={event.time_zone} />

          <EventHost host={event.host} communitySlug={community.slug} isMember={isMember} />

          <EventMeeting event={event} isMember={isMember} />

          {event.body_html && (
            <section aria-labelledby="event-description-title" className="flex flex-col gap-2">
              <h2 id="event-description-title" className="sr-only">
                {EVENT_WORDS.fieldDescription}
              </h2>
              <ErrorBoundary variant="block" label="the description of this event">
                <MarkdownContent html={event.body_html} />
              </ErrorBoundary>
            </section>
          )}
        </article>
      </CommunityShell>
    </AppLayout>
  );
}
