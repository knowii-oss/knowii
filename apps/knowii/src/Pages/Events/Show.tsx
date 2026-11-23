import { useCallback, useMemo, useRef } from 'react';
import { router } from '@inertiajs/react';
import type { Comment, Community, EventPermissions, Space, SpaceContentPayload, SpaceEvent, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, SPACE_TYPE_LABELS, SPACE_URL, useSocket, useTypedPage } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommentSection } from '@/Components/Comments/CommentSection';
import { AddToCalendar } from '@/Components/Events/AddToCalendar';
import { EventActions } from '@/Components/Events/EventActions';
import { EventAnswer } from '@/Components/Events/EventAnswer';
import { EventHost } from '@/Components/Events/EventHost';
import { EventMeeting } from '@/Components/Events/EventMeeting';
import { EventStateLine } from '@/Components/Events/EventStateLine';
import { EventWhen } from '@/Components/Events/EventWhen';
import { EVENT_WORDS } from '@/Components/Events/event-words';
import { useNow } from '@/Components/Events/event-state';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { CoverImage, ErrorBoundary, MarkdownContent } from '@/Components/ui';

/**
 * A load of the props that runs: of the whole page or of the event alone, with the function that cancels it
 */
interface RunningLoad {
  wholePage: boolean;
  cancel: () => void;
}

interface Props {
  // WARNING: aligned with EventController::show
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  // With its description. The server cut the meeting address and the place for a reader who may not see them
  event: SpaceEvent;
  // The comments of the event (#1516), for every reader of the event
  comments: Comment[];
  permissions: EventPermissions;
}

/**
 * The page of an event of an events space (#1514, #1515, ADR 0053, design 6.2). In this order: the title (the only h1),
 * the state as text, the cover, the date in the zone of the reader, "Add to calendar" for a member (#1515), the host,
 * the answer of the reader, where it happens, the description, and after the article the comments (#1516). A visitor and
 * a person who is not a member read it in a public space, without the meeting address: the server cuts it. A visitor
 * reads the comments with the prompt to sign in.
 *
 * Live (never for a visitor): when the space channel says that this event was published, changed or went away, the
 * page loads again, and the server decides what the reader then sees. The changes that the reader makes here load the
 * page themselves. When the places of the event changed (`space.event.answers.changed`), and after each answer of the
 * reader, the page gets the event alone again: the places, the answer and the meeting address are the ones of the server.
 *
 * One load at a time: every load of this page brings the event, and two loads can land in the other order. A new load
 * cancels the one that runs, a save of the answer cancels it too, and no load starts while a save runs: the load after
 * the save brings what the server then holds. An older event never lands after a newer one.
 */
export default function EventPage({ community, space, spaceGroups, event, comments, permissions }: Props) {
  const user = useTypedPage().props.auth.user;
  const now = useNow();
  const isMember = community.is_member === true;
  const spaceChannel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );

  // The load that runs, with the function of Inertia that cancels it. Inertia gives the function before `router.reload`
  // returns, and it does nothing once the answer of the server arrived: that answer lands in its turn
  const running = useRef<RunningLoad | null>(null);
  // A load of the whole page was cancelled, or was asked for while a save ran: the next load is of the whole page
  const wholePageOwed = useRef(false);
  // A save of the answer of the reader runs: it loads the event when it ends
  const savingAnswer = useRef(false);

  const cancelLoad = useCallback(() => {
    const load = running.current;
    if (load === null) {
      return;
    }
    running.current = null;
    wholePageOwed.current ||= load.wholePage;
    load.cancel();
  }, []);

  const load = useCallback(
    (wholePage: boolean) => {
      if (savingAnswer.current) {
        // The load after the save is enough: one load, not two
        wholePageOwed.current ||= wholePage;
        return;
      }
      cancelLoad();
      const all = wholePage || wholePageOwed.current;
      const started: RunningLoad = { wholePage: all, cancel: () => undefined };
      running.current = started;
      router.reload({
        ...(all ? {} : { only: ['event'] }),
        onCancelToken: (token) => {
          started.cancel = token.cancel;
        },
        onSuccess: () => {
          if (all) {
            wholePageOwed.current = false;
          }
        },
        // Also after a cancel: only the load that still runs leaves the place
        onFinish: () => {
          if (running.current === started) {
            running.current = null;
          }
        },
      });
    },
    [cancelLoad],
  );
  // The event alone, without the other props of the page: after an answer, and when the places changed
  const reloadEvent = useCallback(() => load(false), [load]);
  // Every prop: the server decides what the reader then sees
  const reloadPage = useCallback(() => load(true), [load]);

  useSocket({
    channel: spaceChannel,
    // A visitor gets a static page: no connection (#1387, ADR 0044 point 13)
    enabled: !!user,
    // `created` too: it says that a draft was published, and an admin can have the page of that draft open.
    // `answers.changed` (#1515): the places of an event with a limit changed. It names no member
    event: ['space.event.created', 'space.event.updated', 'space.event.deleted', 'space.event.answers.changed'],
    callback: (name, payload) => {
      const { event_cuid: eventCuid, author_cuid: authorCuid } = payload as SpaceContentPayload;
      if (eventCuid !== event.cuid) {
        return;
      }
      if (name === 'space.event.answers.changed') {
        // Only the event: the dialog "Answers", the focus, the status and the alert of the answer group stay. While the
        // answer of the reader is saved, the load after that save is enough
        reloadEvent();
        return;
      }
      // The own changes of the reader already load the page, or leave it (a delete)
      if (authorCuid !== user?.cuid) {
        reloadPage();
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
            <EventActions community={community} space={space} event={event} permissions={permissions} now={now} onChanged={reloadPage} />
          </header>

          {/* An empty description marks a decoration: screen readers skip the picture */}
          {event.cover && <CoverImage src={event.cover.url} alt={event.cover_alt ?? ''} loading="eager" />}

          <EventWhen start={event.starts_at} end={event.ends_at} timeZone={event.time_zone} />

          {/* The calendar file (#1515): for a member, never for a draft. Its name is its words: the title is the heading */}
          <AddToCalendar communityCuid={community.cuid} spaceCuid={space.cuid} event={event} isMember={isMember} />

          <EventHost host={event.host} communitySlug={community.slug} isMember={isMember} />

          <EventAnswer
            community={community}
            space={space}
            event={event}
            isMember={isMember}
            now={now}
            onSaving={(saving) => {
              savingAnswer.current = saving;
              if (saving) {
                // A load that started before the save holds the answer of before it
                cancelLoad();
              }
            }}
            onSaved={reloadEvent}
            onGone={reloadPage}
          />

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

        {/* The comments come last, after the article (design 6.2 point 2), with their own live updates. A draft takes
            no comments and never holds one: no section */}
        {event.state !== 'draft' && (
          <CommentSection
            subject={{ communityCuid: community.cuid, spaceCuid: space.cuid, eventCuid: event.cuid }}
            initialComments={comments}
            currentUserCuid={user?.cuid ?? null}
            canComment={permissions.canComment}
            canModerate={permissions.canModerate}
          />
        )}
      </CommunityShell>
    </AppLayout>
  );
}
