import { useCallback, useRef, useState } from 'react';
import type { EventScope, Paginated, SpaceEvent } from '@knowii/common';
import {
  EVENT_URL,
  EVENTS_PER_PAGE,
  EVENTS_SCOPE_QUERY_PARAM,
  knowiiApiClient,
  MY_EVENTS_URL,
  SPACE_TYPE_LABELS,
  useAppData,
} from '@knowii/common';
import { EmptyState, ErrorBoundary, LinkTabs, LoadMoreButton, useFocusAfterRender } from '@/Components/ui';
import { AddToCalendar } from './AddToCalendar';
import { useNow } from './event-state';
import { EVENT_WORDS } from './event-words';
import { EventCard } from './EventCard';

export interface MyEventListProps {
  /**
   * The first page of the list of this scope, from the server
   */
  initialEvents: Paginated<SpaceEvent>;
  /**
   * The list that is open: "Upcoming" or "Past". A change of scope starts a new list, and keeps the two links.
   */
  scope: EventScope;
  /**
   * The reader is a member of more than one community: each row names the community of its event
   */
  showCommunity: boolean;
  /**
   * The cuid of the reader: the rows of the events that they host say "You host"
   */
  readerCuid: string;
}

/**
 * The list of "My events" (#1515, design 6.8 and 11.3): two links "Upcoming" and "Past" (links, not the tabs of ARIA:
 * each one loads a list) and a real list of rows. A row is the card of an event without its cover, with the space (and
 * the community, for a member of several) above the title, the labels, and "Add to calendar" on every event that is not
 * a draft. More events load 20 at a time: the focus goes to the title of the first new row and a status says how many
 * loaded.
 *
 * It follows the list of a space (`EventList`), without a space and without live updates: the page shows what the
 * server gave when it loaded, and sends a request only when the reader presses the button that loads more.
 */
export function MyEventList({ initialEvents, scope, showCommunity, readerCuid }: MyEventListProps) {
  // What the status says, with the scope that it was said for
  const [status, setStatus] = useState<{ scope: EventScope; text: string }>({ scope, text: '' });
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

  const listUrl = route(MY_EVENTS_URL);

  return (
    <section aria-label={EVENT_WORDS.myEvents} className="flex flex-col gap-4 @container">
      <LinkTabs
        ariaLabel={EVENT_WORDS.myEvents}
        items={[
          { label: EVENT_WORDS.upcoming, href: listUrl, current: scope === 'upcoming' },
          { label: EVENT_WORDS.past, href: `${listUrl}?${EVENTS_SCOPE_QUERY_PARAM}=past`, current: scope === 'past' },
        ]}
      />

      <MyEventRows
        // A change of scope starts a new list: the rows, the next page and a load in progress belong to one scope
        key={scope}
        initialEvents={initialEvents}
        scope={scope}
        showCommunity={showCommunity}
        readerCuid={readerCuid}
        onStatus={say}
      />

      {/* "Past events: 12", "20 more loaded": polite, and always in the page so that a screen reader reads each change */}
      <p role="status" aria-live="polite" className="sr-only">
        {status.scope === scope ? status.text : ''}
      </p>
    </section>
  );
}

interface MyEventRowsProps extends MyEventListProps {
  /**
   * The text of the status of the list of a scope ("20 more loaded"), or an empty text
   */
  onStatus: (scope: EventScope, text: string) => void;
}

/**
 * The rows of one scope, with the button that loads more. The list above gives it the scope as its key: a change of scope
 * starts a new one, and an answer of the server for the old scope is lost with it.
 */
function MyEventRows({ initialEvents, scope, showCommunity, readerCuid, onStatus }: MyEventRowsProps) {
  const { showToast } = useAppData();
  const now = useNow();
  const listRef = useRef<HTMLUListElement>(null);
  const [events, setEvents] = useState<SpaceEvent[]>(initialEvents.items);
  const [nextOffset, setNextOffset] = useState<number | null>(initialEvents.pagination.nextOffset);
  const [loading, setLoading] = useState(false);
  const focusAfterRender = useFocusAfterRender();

  // The title link of a row. The exact value is compared: no selector is built from an identifier
  const linkOf = (eventCuid: string) =>
    Array.from(listRef.current?.querySelectorAll<HTMLElement>('[data-event-link]') ?? []).find(
      (link) => link.dataset['eventLink'] === eventCuid,
    );

  const loadMore = async () => {
    if (nextOffset === null || loading) {
      return;
    }
    setLoading(true);
    onStatus(scope, '');
    const result = await knowiiApiClient.users.events.list(scope, EVENTS_PER_PAGE, nextOffset);
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
      // The button may go away with the last page: the reader continues on the first new row
      focusAfterRender(() => linkOf(first.cuid));
    }
  };

  return (
    <>
      {events.length === 0 ? (
        <EmptyState
          icon={SPACE_TYPE_LABELS.events.emoji}
          title={scope === 'past' ? EVENT_WORDS.emptyPastTitle : EVENT_WORDS.emptyUpcomingTitle}
          description={scope === 'past' ? EVENT_WORDS.myEventsEmptyPastDescription : EVENT_WORDS.myEventsEmptyUpcomingDescription}
        />
      ) : (
        <ul ref={listRef} aria-label={scope === 'past' ? EVENT_WORDS.pastList : EVENT_WORDS.upcomingList} className="flex flex-col gap-3">
          {events.map((event) => {
            const { community, space } = event;
            // The server gives both on every row of this list
            if (!community || !space) {
              return null;
            }
            return (
              <li key={event.cuid} data-event-card={event.cuid}>
                <ErrorBoundary variant="block" label={`the event "${event.title}"`}>
                  <EventCard
                    event={event}
                    href={route(EVENT_URL, { communitySlug: community.slug, spaceSlug: space.slug, eventSlug: event.slug })}
                    now={now}
                    cover={false}
                    context={EVENT_WORDS.myEventsContext(showCommunity ? community.name : null, space.name)}
                    readerCuid={readerCuid}
                    actions={
                      <AddToCalendar communityCuid={community.cuid} spaceCuid={space.cuid} event={event} isMember={true} withTitle={true} />
                    }
                  />
                </ErrorBoundary>
              </li>
            );
          })}
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
