import type { Author, Community, EventFormPermissions, Space, SpaceEvent, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, EVENT_URL, SPACE_TYPE_LABELS, SPACE_URL, useTypedPage } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { EventForm } from '@/Components/Events/EventForm';
import { EVENT_WORDS } from '@/Components/Events/event-words';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';

interface Props {
  // WARNING: aligned with EventController::form
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  // The event to change, with its description. Null: a new event
  event: SpaceEvent | null;
  // The zones that the server accepts
  timeZones: string[];
  permissions: EventFormPermissions;
}

/**
 * The form of the host of an event (#1514, ADR 0053, design 6.3): a page, not a dialog, because it is long and a page
 * is simpler on a phone. A new event (`event` is null), or a change of an event that the reader may change.
 */
export default function EventFormPage({ community, space, spaceGroups, event, timeZones, permissions }: Props) {
  const user = useTypedPage().props.auth.user;
  const title = event ? EVENT_WORDS.editEventTitle : EVENT_WORDS.newEventTitle;
  const slugs = { communitySlug: community.slug, spaceSlug: space.slug };
  // The page is for signed-in people: the reader is the host of a new event until they name another member
  const currentUser: Author = {
    cuid: user?.cuid ?? '',
    name: user?.name ?? '',
    username: user?.username ?? '',
    avatar_url: user?.avatar_url ?? null,
  };

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    {
      label: space.name,
      href: route(SPACE_URL, slugs),
      icon: <span aria-hidden="true">{space.emoji || SPACE_TYPE_LABELS[space.type].emoji}</span>,
    },
    ...(event ? [{ label: event.title, href: route(EVENT_URL, { ...slugs, eventSlug: event.slug }) }] : []),
    { label: title },
  ];

  return (
    <AppLayout
      browserPageTitle={`${event ? `${title}: ${event.title}` : title} - ${space.name} - ${community.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      <CommunityShell community={community} spaceGroups={spaceGroups} activeSpaceCuid={space.cuid} contentWidth="readable">
        <div className="flex flex-col gap-6">
          <h1 id="event-form-title" className="text-2xl font-extrabold wrap-anywhere">
            {title}
          </h1>
          <EventForm
            // Another event (after "Duplicate", the form of the copy) starts a new form
            key={event?.cuid ?? 'new'}
            community={community}
            space={space}
            event={event}
            timeZones={timeZones}
            permissions={permissions}
            currentUser={currentUser}
          />
        </div>
      </CommunityShell>
    </AppLayout>
  );
}
