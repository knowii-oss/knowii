import type { ReactNode, RefObject } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { Community, EventScope, Paginated, Space, SpaceContentPayload, SpaceEvent } from '@knowii/common';
import {
  EVENT_CREATE_URL,
  EVENT_URL,
  EVENTS_PER_PAGE,
  EVENTS_SCOPE_QUERY_PARAM,
  HttpStatus,
  knowiiApiClient,
  SPACE_TYPE_LABELS,
  SPACE_URL,
  useAppData,
  useSocket,
} from '@knowii/common';
import { FaPlus } from 'react-icons/fa6';
import { useIsVisitor } from '@/Components/Reader/Reader';
import { EmptyState, ErrorBoundary, LinkButton, LinkTabs, LoadMoreButton, useFocusAfterRender } from '@/Components/ui';
import { comparePast, compareUpcoming, useNow } from './event-state';
import { EVENT_WORDS } from './event-words';
import { EventCard } from './EventCard';

export interface EventListProps {
  community: Community;
  space: Space;
  /**
   * The first page of the list of this scope, from the server
   */
  initialEvents: Paginated<SpaceEvent>;
  /**
   * The list that is open: "Upcoming" or "Past". A change of scope starts a new list, and keeps the two links.
   */
  scope: EventScope;
  /**
   * The reader may create an event in the space (its posting policy)
   */
  canContribute: boolean;
}

/**
 * The events of an events space (#1514, design 6.1 and 11.3): two links "Upcoming" and "Past" (links, not the tabs of
 * ARIA: each one loads a list), "New event" for the people who may create one, and a real list of cards. "Upcoming"
 * holds the drafts that the reader may see first, then the next start first; "Past" the newest start first. More
 * events load 20 at a time: the focus goes to the first new card and a status says how many loaded.
 *
 * A press on "Upcoming" or "Past" loads the other list in the same page. The two links stay: the one that was pressed
 * keeps the focus, and the status says which list opened and how many events it holds. Only the cards and their state
 * start again (`EventCards` has the scope as its key).
 *
 * Live (ADR 0053 point 9, never for a visitor): the space channel names an event that was published, changed or removed,
 * or whose places changed (#1515). The list gets that one event again and inserts, replaces or removes its card; an
 * event that the reader may no longer see (403, 404) goes. A card that has the focus gives it to the next card before
 * it goes.
 */
export function EventList({ community, space, initialEvents, scope, canContribute }: EventListProps) {
  const sectionRef = useRef<HTMLElement>(null);
  // What the status says, with the scope that it was said for
  const [status, setStatus] = useState<{ scope: EventScope; text: string }>({ scope, text: '' });
  // The number of cards of the open list, from the list itself
  const [shown, setShown] = useState<{ scope: EventScope; count: number }>({ scope, count: initialEvents.items.length });
  if (status.scope !== scope) {
    // The other list opened (the state of the page stays over the visit): say so, the first page has no status
    const name = scope === 'past' ? EVENT_WORDS.pastList : EVENT_WORDS.upcomingList;
    const empty = scope === 'past' ? EVENT_WORDS.emptyPastTitle : EVENT_WORDS.emptyUpcomingTitle;
    const count = initialEvents.pagination.totalCount;
    setStatus({ scope, text: count === 0 ? empty : EVENT_WORDS.scopeOpened(name, count) });
  }
  // An answer of the server for the list that closed says nothing about the list that is open
  const say = useCallback(
    (scopeOfList: EventScope, text: string) => setStatus((current) => (current.scope === scopeOfList ? { ...current, text } : current)),
    [],
  );
  const count = useCallback(
    (scopeOfList: EventScope, cards: number) =>
      setShown((current) => (current.scope === scopeOfList && current.count === cards ? current : { scope: scopeOfList, count: cards })),
    [],
  );
  // Until the new list reports its cards, the first page of the server counts
  const hasEvents = (shown.scope === scope ? shown.count : initialEvents.items.length) > 0;

  const spaceUrl = route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug });
  const newEventLink = canContribute ? (
    <LinkButton href={route(EVENT_CREATE_URL, { communitySlug: community.slug, spaceSlug: space.slug })}>
      <FaPlus aria-hidden="true" />
      {EVENT_WORDS.newEvent}
    </LinkButton>
  ) : null;

  return (
    <section ref={sectionRef} aria-label={`Events in ${space.name}`} className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <LinkTabs
          ariaLabel={EVENT_WORDS.scopesLabel}
          items={[
            { label: EVENT_WORDS.upcoming, href: spaceUrl, current: scope === 'upcoming' },
            { label: EVENT_WORDS.past, href: `${spaceUrl}?${EVENTS_SCOPE_QUERY_PARAM}=past`, current: scope === 'past' },
          ]}
        />
        {hasEvents && newEventLink}
      </div>

      <EventCards
        // A change of scope starts a new list: the cards, the next page and a load in progress belong to one scope
        key={scope}
        community={community}
        space={space}
        initialEvents={initialEvents}
        scope={scope}
        sectionRef={sectionRef}
        emptyAction={newEventLink}
        onStatus={say}
        onCount={count}
      />

      {/* "Past events: 12", "20 more loaded": polite, and always in the page so that a screen reader reads each change */}
      <p role="status" aria-live="polite" className="sr-only">
        {status.scope === scope ? status.text : ''}
      </p>
    </section>
  );
}

interface EventCardsProps {
  community: Community;
  space: Space;
  initialEvents: Paginated<SpaceEvent>;
  scope: EventScope;
  /**
   * The section of the list: the cards and the two links are found in it
   */
  sectionRef: RefObject<HTMLElement | null>;
  /**
   * "New event" for the empty list, or nothing
   */
  emptyAction: ReactNode;
  /**
   * The text of the status of the list of a scope ("20 more loaded"), or an empty text
   */
  onStatus: (scope: EventScope, text: string) => void;
  /**
   * The number of cards, at the start and after each change
   */
  onCount: (scope: EventScope, cards: number) => void;
}

/**
 * The cards of one scope, with the button that loads more and the live updates. The list above gives it the scope as
 * its key: a change of scope starts a new one, and an answer of the server for the old scope is lost with it.
 */
function EventCards({ community, space, initialEvents, scope, sectionRef, emptyAction, onStatus, onCount }: EventCardsProps) {
  const { showToast } = useAppData();
  const isVisitor = useIsVisitor();
  const now = useNow();
  const [events, setEvents] = useState<SpaceEvent[]>(initialEvents.items);
  const [nextOffset, setNextOffset] = useState<number | null>(initialEvents.pagination.nextOffset);
  const [loading, setLoading] = useState(false);
  const focusAfterRender = useFocusAfterRender();
  // The newest state, for the answers of the server that come after a render
  const hasMoreRef = useRef(nextOffset !== null);
  useEffect(() => {
    hasMoreRef.current = nextOffset !== null;
  }, [nextOffset]);
  // "New event" sits next to the two links while the list has cards, and in the empty state when it has none
  useEffect(() => {
    onCount(scope, events.length);
  }, [onCount, scope, events.length]);

  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );
  // The link of the card of an event. The exact value is compared: no selector is built from an identifier
  const linkOf = (eventCuid: string) =>
    Array.from(sectionRef.current?.querySelectorAll<HTMLElement>('[data-event-link]') ?? []).find(
      (link) => link.dataset['eventLink'] === eventCuid,
    );

  /**
   * Remove the card of an event. When the focus is in it, the link of the next card takes it first (else the one of
   * the card before): a card that goes away would drop the focus on the page.
   */
  const removeCard = useCallback(
    (eventCuid: string) => {
      const section = sectionRef.current;
      const item = Array.from(section?.querySelectorAll<HTMLElement>('[data-event-card]') ?? []).find(
        (card) => card.dataset['eventCard'] === eventCuid,
      );
      if (item && item.contains(document.activeElement)) {
        const neighbour = item.nextElementSibling ?? item.previousElementSibling;
        // The last card of the list: the link of the open list takes the focus
        const target =
          neighbour?.querySelector<HTMLElement>('[data-event-link]') ?? section?.querySelector<HTMLElement>('a[aria-current="page"]');
        target?.focus();
      }
      setEvents((current) => current.filter((event) => event.cuid !== eventCuid));
    },
    [sectionRef],
  );

  /**
   * Get one event again and put its card where it belongs. An event of the other list, and an event that the reader
   * may not see (403) or that is gone (404), leave this list.
   */
  const refreshEvent = useCallback(
    async (eventCuid: string) => {
      const result = await knowiiApiClient.communities.events.get(community.cuid, space.cuid, eventCuid);
      if (!result.ok) {
        if (result.status === HttpStatus.FORBIDDEN || result.status === HttpStatus.NOT_FOUND) {
          removeCard(eventCuid);
        }
        return;
      }
      const event = result.data;
      if (event.is_past !== (scope === 'past')) {
        removeCard(eventCuid);
        return;
      }
      setEvents((current) => {
        const known = current.some((item) => item.cuid === eventCuid);
        const merged = [...current.filter((item) => item.cuid !== eventCuid), event].sort(scope === 'past' ? comparePast : compareUpcoming);
        // A new event that sorts after everything that is loaded belongs to a page that is not loaded yet
        const isLast = merged[merged.length - 1]?.cuid === eventCuid;
        return !known && isLast && hasMoreRef.current && current.length > 0 ? current : merged;
      });
    },
    [community.cuid, space.cuid, scope, removeCard],
  );

  useSocket({
    channel,
    // A visitor gets a static page: no connection (#1387, ADR 0044 point 13)
    enabled: !isVisitor,
    // `answers.changed` (#1515): the places of an event with a limit changed, and its card says "28 places left" or "Full"
    event: ['space.event.created', 'space.event.updated', 'space.event.deleted', 'space.event.answers.changed'],
    callback: (_name, payload) => {
      const { event_cuid: eventCuid } = payload as SpaceContentPayload;
      // `space.event.deleted` too: it also says that an event went back to draft, and its host, the owner and the
      // admins keep the card of a draft. The answer of the server decides: 403 or 404 removes the card
      if (eventCuid) {
        void refreshEvent(eventCuid);
      }
    },
  });

  const loadMore = async () => {
    if (nextOffset === null || loading) {
      return;
    }
    setLoading(true);
    onStatus(scope, '');
    const result = await knowiiApiClient.communities.events.list(community.cuid, space.cuid, scope, EVENTS_PER_PAGE, nextOffset);
    setLoading(false);
    if (!result.ok) {
      // The message of the server
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    // An event can move between pages when the list changes on the server: skip the ones already shown
    const added = result.data.items.filter((event) => !events.some((shown) => shown.cuid === event.cuid));
    setEvents((current) => [...current, ...added.filter((event) => !current.some((shown) => shown.cuid === event.cuid))]);
    setNextOffset(result.data.pagination.nextOffset);
    onStatus(scope, EVENT_WORDS.loaded(added.length));
    const first = added[0];
    if (first) {
      // The button may go away with the last page: the reader continues on the first new card
      focusAfterRender(() => linkOf(first.cuid));
    }
  };

  return (
    <>
      {events.length === 0 ? (
        <EmptyState
          icon={SPACE_TYPE_LABELS.events.emoji}
          title={scope === 'past' ? EVENT_WORDS.emptyPastTitle : EVENT_WORDS.emptyUpcomingTitle}
          description={scope === 'past' ? EVENT_WORDS.emptyPastDescription : EVENT_WORDS.emptyUpcomingDescription}
          action={emptyAction}
        />
      ) : (
        <ul aria-label={scope === 'past' ? EVENT_WORDS.pastList : EVENT_WORDS.upcomingList} className="flex flex-col gap-3">
          {events.map((event) => (
            <li key={event.cuid} data-event-card={event.cuid}>
              <ErrorBoundary variant="block" label={`the event "${event.title}"`}>
                <EventCard
                  event={event}
                  href={route(EVENT_URL, { communitySlug: community.slug, spaceSlug: space.slug, eventSlug: event.slug })}
                  now={now}
                />
              </ErrorBoundary>
            </li>
          ))}
        </ul>
      )}

      {nextOffset !== null && (
        <LoadMoreButton onClick={() => void loadMore()} loading={loading}>
          {scope === 'past' ? EVENT_WORDS.loadOlder : EVENT_WORDS.loadMore}
        </LoadMoreButton>
      )}
    </>
  );
}
