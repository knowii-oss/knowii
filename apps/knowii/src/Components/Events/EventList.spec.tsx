import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { EventScope, SpaceEvent } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { VisitorProvider } from '@/Components/Reader/Reader';
import type { ToolkitLinkProps } from '@/Components/ui';
import { AccountTimeZoneProvider, Toaster, ToolkitProvider } from '@/Components/ui';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { EVENT_TEST_NOW, eventFixture, eventsCommunity, eventsPagination, eventsRoute, eventsSpace } from '@/test-utils/events';
import { EVENT_WORDS } from './event-words';
import { EventList } from './EventList';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { auth: { user: null } } }),
  Link: ({
    children,
    href,
    preserveState: _preserveState,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveState?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

// What a press on a link of the toolkit does: the visit of the router, when a test gives one
const shared: { visit: ((href: string) => void) | null } = { visit: null };

/**
 * The link of the application for the toolkit ("Upcoming", "Past", "New event"): a press visits, and stays in the page
 */
function VisitLink({ href, children, className, 'aria-current': ariaCurrent }: ToolkitLinkProps) {
  return (
    <a
      href={href}
      className={className}
      aria-current={ariaCurrent}
      onClick={(event) => {
        if (shared.visit) {
          event.preventDefault();
          shared.visit(href);
        }
      }}
    >
      {children}
    </a>
  );
}

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
  // jsdom has no scrollIntoView: the first new card scrolls into view after a load
  Element.prototype.scrollIntoView = vi.fn();
});

const channel = 'community.c1.space.s1';
const at = (iso: string) => ({ starts_at: iso, ends_at: new Date(new Date(iso).getTime() + 3_600_000).toISOString() });

interface Options {
  scope?: EventScope;
  events?: SpaceEvent[];
  nextOffset?: number | null;
  canContribute?: boolean;
  visitor?: boolean;
  zone?: string | null;
}

function tree({
  scope = 'upcoming',
  events,
  nextOffset = null,
  canContribute = false,
  visitor = false,
  zone = 'America/New_York',
}: Options = {}) {
  const items = events ?? [eventFixture('e1', 'Weekly review'), eventFixture('e2', 'Workshop', at('2026-11-20T16:00:00.000000Z'))];
  return (
    <AppDataProvider>
      <VisitorProvider value={visitor}>
        <AccountTimeZoneProvider value={zone}>
          <ToolkitProvider linkComponent={VisitLink}>
            <main>
              <EventList
                community={eventsCommunity}
                space={eventsSpace}
                initialEvents={{ items, pagination: eventsPagination(nextOffset, items.length + (nextOffset === null ? 0 : 5)) }}
                scope={scope}
                canContribute={canContribute}
              />
            </main>
          </ToolkitProvider>
          <Toaster />
        </AccountTimeZoneProvider>
      </VisitorProvider>
    </AppDataProvider>
  );
}

const renderList = (options: Options = {}) => render(tree(options));

const titles = () => screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent);
// The status of the list (the toasts have their own)
const status = () => {
  const statuses = within(screen.getByRole('region', { name: 'Events in Office hours' })).getAllByRole('status');
  // The spinner of a busy button is a status too: the status of the list is the paragraph
  return statuses.find((element) => element.tagName === 'P') as HTMLElement;
};

describe('the list of an events space (#1514, design 6.1 and 11.3)', () => {
  beforeEach(() => {
    // Only the clock of `new Date()`: the timers stay real, so that the answers of the tests arrive
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(EVENT_TEST_NOW);
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    shared.visit = null;
  });

  describe('the cards (rule 15)', () => {
    it('is a real list: each card is a list item with a heading that links to the page of the event', () => {
      renderList();

      const list = screen.getByRole('list', { name: 'Upcoming events' });
      const items = within(list).getAllByRole('listitem');
      expect(items).toHaveLength(2);
      const first = items[0] as HTMLElement;
      expect(within(first).getByRole('heading', { level: 2, name: 'Weekly review' })).toBeInTheDocument();
      expect(within(first).getByRole('link', { name: 'Weekly review' })).toHaveAttribute(
        'href',
        '/community/tests/space/office-hours/event/weekly-review',
      );
      expect(within(first).getByRole('article', { name: 'Weekly review' })).toBeInTheDocument();
    });

    it('names the host, or "Deleted member" when the account of the host is gone', () => {
      renderList({ events: [eventFixture('e1', 'Weekly review'), eventFixture('e2', 'Workshop', { host: null })] });

      expect(screen.getByText('Hosted by Ada Lovelace')).toBeInTheDocument();
      expect(screen.getByText('Hosted by Deleted member')).toBeInTheDocument();
      // The name of the host is text: the list has no link to a member
      expect(screen.queryByRole('link', { name: /Ada Lovelace/ })).toBeNull();
    });

    it('shows the cover with the description of the host, and as a decoration without one', () => {
      const cover = {
        cuid: 'up1',
        url: '/uploads/cover.webp',
        mime_type: 'image/webp',
        width: 1600,
        height: 900,
        size_bytes: 1000,
        alt: 'Cover of an event',
      };
      renderList({
        events: [
          eventFixture('e1', 'Weekly review', { cover, cover_alt: 'The team at a table' }),
          eventFixture('e2', 'Workshop', { cover: { ...cover, cuid: 'up2', url: '/uploads/other.webp' }, cover_alt: null }),
        ],
      });

      expect(screen.getByRole('img', { name: 'The team at a table' })).toHaveAttribute('src', '/uploads/cover.webp');
      // The text of the upload ("Cover of an event") is never the description of the cover
      expect(screen.queryByRole('img', { name: 'Cover of an event' })).toBeNull();
      const decoration = document.querySelector('img[src="/uploads/other.webp"]');
      expect(decoration).toHaveAttribute('alt', '');
    });

    it('writes the states as text labels: "Draft", "Cancelled" and "Happening now"', () => {
      renderList({
        events: [
          eventFixture('e1', 'A draft', { state: 'draft' }),
          eventFixture('e2', 'Called off', { state: 'cancelled' }),
          // Started 10 minutes ago, ends in 50 minutes
          eventFixture('e3', 'In progress', at(new Date(EVENT_TEST_NOW.getTime() - 600_000).toISOString())),
          eventFixture('e4', 'Later'),
        ],
      });
      const card = (name: string) => screen.getByRole('article', { name });

      expect(within(card('A draft')).getByText('Draft')).toBeInTheDocument();
      expect(within(card('Called off')).getByText('Cancelled')).toBeInTheDocument();
      expect(within(card('In progress')).getByText('Happening now')).toBeInTheDocument();
      for (const label of ['Draft', 'Cancelled', 'Happening now']) {
        expect(within(card('Later')).queryByText(label)).toBeNull();
      }
      // A cancelled event and a draft are never "happening", also inside their hours
      const running = at(new Date(EVENT_TEST_NOW.getTime() - 600_000).toISOString());
      renderList({ events: [eventFixture('e5', 'Cancelled and running', { ...running, state: 'cancelled' })] });
      expect(within(card('Cancelled and running')).queryByText('Happening now')).toBeNull();
    });
  });

  describe('the times (rule 11)', () => {
    it('is a time element with the UTC start, the short form for the eyes and the long form for a screen reader', () => {
      renderList();

      const time = within(screen.getByRole('article', { name: 'Weekly review' }))
        .getByText('Wed 18 Nov, 11:00 Eastern Standard Time')
        .closest('time');
      expect(time).toHaveAttribute('datetime', '2026-11-18T16:00:00.000Z');
      expect(time).not.toHaveAttribute('title');
      expect(within(time as HTMLElement).getByText('Wed 18 Nov, 11:00 Eastern Standard Time')).toHaveAttribute('aria-hidden', 'true');
      expect(within(time as HTMLElement).getByText('Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time')).toHaveClass('sr-only');
      // Never a relative time
      expect(screen.queryByText(/ago|^in \d|Starts in/i)).toBeNull();
    });

    it('follows the zone of the account: the same event is on another day in Singapore', () => {
      renderList({ zone: 'Asia/Singapore' });

      expect(screen.getByText('Thu 19 Nov, 00:00 Singapore Standard Time')).toBeInTheDocument();
      expect(screen.getByText('Thursday 19 November, 00:00 to 01:00 Singapore Standard Time')).toBeInTheDocument();
    });

    it('uses the zone of the browser for a reader without a zone (a visitor)', () => {
      renderList({ zone: null, visitor: true });
      const browser = Intl.DateTimeFormat().resolvedOptions().timeZone;
      const expected = new Intl.DateTimeFormat('en-GB', { timeZone: browser, timeZoneName: 'long' })
        .formatToParts(new Date('2026-11-18T16:00:00Z'))
        .find((part) => part.type === 'timeZoneName')?.value;

      expect(screen.getAllByText(new RegExp(`${expected}$`)).length).toBeGreaterThan(0);
    });
  });

  describe('"Upcoming" and "Past" (rule 14)', () => {
    it('are two links in a navigation, with the open list marked by aria-current', () => {
      renderList({ scope: 'upcoming' });

      const navigation = screen.getByRole('navigation', { name: 'Events' });
      const upcoming = within(navigation).getByRole('link', { name: 'Upcoming' });
      const past = within(navigation).getByRole('link', { name: 'Past' });
      expect(upcoming).toHaveAttribute('href', '/community/tests/space/office-hours');
      expect(upcoming).toHaveAttribute('aria-current', 'page');
      expect(past).toHaveAttribute('href', '/community/tests/space/office-hours?scope=past');
      expect(past).not.toHaveAttribute('aria-current');
      // The marker is a line and a heavier text, not a color alone
      expect(upcoming.className).toContain('border-b-2');
      expect(upcoming.className).toContain('font-bold');
      // Links, not the tabs of ARIA
      expect(screen.queryByRole('tablist')).toBeNull();
      expect(screen.queryByRole('tab')).toBeNull();
    });

    it('marks "Past" when the scope of the page is past', () => {
      renderList({ scope: 'past', events: [eventFixture('e1', 'Last week', { is_past: true })] });

      expect(screen.getByRole('link', { name: 'Past' })).toHaveAttribute('aria-current', 'page');
      expect(screen.getByRole('link', { name: 'Upcoming' })).not.toHaveAttribute('aria-current');
      expect(screen.getByRole('list', { name: 'Past events' })).toBeInTheDocument();
    });

    describe('a press on the other link (rules 14 and 34)', () => {
      const pastEvents = [
        eventFixture('p1', 'Last week', { is_past: true }),
        eventFixture('p2', 'Last month', { is_past: true }),
        eventFixture('p3', 'Last year', { is_past: true }),
      ];
      // The visit keeps the state of the page: the same list gets the props of the other scope
      const visitWith = (view: ReturnType<typeof render>, past: Options) => {
        shared.visit = (href) => view.rerender(tree(href.endsWith('scope=past') ? { scope: 'past', ...past } : {}));
      };

      it('keeps the focus on the link that the keyboard pressed, and a status names the list that opened with its count', async () => {
        const user = userEvent.setup();
        const view = renderList();
        // 3 past events on this page, 5 more on the server
        visitWith(view, { events: pastEvents, nextOffset: 20 });
        const upcoming = screen.getByRole('link', { name: 'Upcoming' });
        const past = screen.getByRole('link', { name: 'Past' });
        // The first page says nothing
        expect(status()).toBeEmptyDOMElement();

        await user.tab();
        expect(upcoming).toHaveFocus();
        await user.tab();
        expect(past).toHaveFocus();
        await user.keyboard('{Enter}');

        expect(titles()).toEqual(['Last week', 'Last month', 'Last year']);
        // The same two links: they did not go away with the old list
        expect(screen.getByRole('link', { name: 'Past' })).toBe(past);
        expect(screen.getByRole('link', { name: 'Upcoming' })).toBe(upcoming);
        expect(past).toHaveFocus();
        expect(past).toHaveAttribute('aria-current', 'page');
        expect(status()).toHaveTextContent('Past events: 8');
        expect(status()).toHaveAttribute('aria-live', 'polite');

        await user.tab({ shift: true });
        await user.keyboard('{Enter}');

        expect(titles()).toEqual(['Weekly review', 'Workshop']);
        expect(upcoming).toHaveFocus();
        expect(upcoming).toHaveAttribute('aria-current', 'page');
        expect(status()).toHaveTextContent('Upcoming events: 2');
      });

      it('says that the list that opened is empty, and moves "New event" to the empty state', async () => {
        const user = userEvent.setup();
        const view = render(tree({ canContribute: true }));
        shared.visit = (href) =>
          view.rerender(tree(href.endsWith('scope=past') ? { scope: 'past', events: [], canContribute: true } : { canContribute: true }));
        const newEvent = () => screen.getAllByRole('link', { name: 'New event' });
        expect(newEvent()).toHaveLength(1);
        expect(newEvent()[0]?.closest('[data-slot="empty-state"]') ?? null).toBeNull();

        await user.click(screen.getByRole('link', { name: 'Past' }));

        expect(status()).toHaveTextContent('No past events');
        // One "New event", never two: next to the links with cards, in the empty state without
        await waitFor(() => expect(newEvent()).toHaveLength(1));
        expect(screen.getByText('Events that ended will show here.')).toBeInTheDocument();
      });

      it('starts the cards again: an answer for the list that closed changes neither the new list nor its status', async () => {
        const user = userEvent.setup();
        let answer: (value: Awaited<ReturnType<typeof knowiiApiClient.communities.events.list>>) => void = () => undefined;
        vi.spyOn(knowiiApiClient.communities.events, 'list').mockReturnValue(
          new Promise((resolve) => {
            answer = resolve;
          }),
        );
        const view = renderList({ nextOffset: 20 });
        shared.visit = (href) =>
          view.rerender(tree(href.endsWith('scope=past') ? { scope: 'past', events: pastEvents } : { nextOffset: 20 }));

        await user.click(screen.getByRole('button', { name: 'Load more' }));
        await user.click(screen.getByRole('link', { name: 'Past' }));
        expect(status()).toHaveTextContent('Past events: 3');
        // The answer of "Load more" of "Upcoming" comes after the change
        await act(async () => {
          answer({ ok: true, message: '', data: { items: [eventFixture('e3', 'Third')], pagination: eventsPagination(null, 3) } });
          await Promise.resolve();
        });

        expect(titles()).toEqual(['Last week', 'Last month', 'Last year']);
        expect(status()).toHaveTextContent('Past events: 3');
        expect(screen.queryByRole('button', { name: 'Load older' })).toBeNull();
      });
    });
  });

  describe('"New event" (rule 13)', () => {
    it('is a link to the form for a reader who may create an event', () => {
      renderList({ canContribute: true });

      expect(screen.getByRole('link', { name: 'New event' })).toHaveAttribute('href', '/community/tests/space/office-hours/events/new');
    });

    it('does not show to a member who may not create, nor to a visitor', () => {
      renderList({ canContribute: false });
      renderList({ canContribute: false, visitor: true });

      expect(screen.queryByRole('link', { name: 'New event' })).toBeNull();
    });
  });

  describe('more events (rule 16)', () => {
    const more = [eventFixture('e2', 'Workshop'), eventFixture('e3', 'Third'), eventFixture('e4', 'Fourth')];

    it('loads the next 20 of "Upcoming", moves the focus to the first new card and says the real count', async () => {
      const list = vi
        .spyOn(knowiiApiClient.communities.events, 'list')
        .mockResolvedValue({ ok: true, message: '', data: { items: more, pagination: eventsPagination(null, 4) } });
      renderList({ nextOffset: 20 });

      await userEvent.click(screen.getByRole('button', { name: 'Load more' }));

      expect(list).toHaveBeenCalledWith('c1', 's1', 'upcoming', 20, 20);
      // "Workshop" was already shown: 2 new cards, not 3
      await waitFor(() => expect(titles()).toEqual(['Weekly review', 'Workshop', 'Third', 'Fourth']));
      expect(status()).toHaveTextContent('2 more loaded');
      expect(status()).toHaveAttribute('aria-live', 'polite');
      await waitFor(() => expect(screen.getByRole('link', { name: 'Third' })).toHaveFocus());
      // The last page: the button goes
      expect(screen.queryByRole('button', { name: 'Load more' })).toBeNull();
    });

    it('is "Load older" in the past list, and asks for the past scope', async () => {
      const list = vi.spyOn(knowiiApiClient.communities.events, 'list').mockResolvedValue({
        ok: true,
        message: '',
        data: { items: [eventFixture('e9', 'Long ago', { is_past: true })], pagination: eventsPagination(40, 60) },
      });
      renderList({ scope: 'past', nextOffset: 20, events: [eventFixture('e1', 'Last week', { is_past: true })] });

      expect(screen.queryByRole('button', { name: 'Load more' })).toBeNull();
      await userEvent.click(screen.getByRole('button', { name: 'Load older' }));

      expect(list).toHaveBeenCalledWith('c1', 's1', 'past', 20, 20);
      await waitFor(() => expect(status()).toHaveTextContent('1 more loaded'));
      await waitFor(() => expect(screen.getByRole('link', { name: 'Long ago' })).toHaveFocus());
      // More pages: the button stays, for the next offset
      await userEvent.click(screen.getByRole('button', { name: 'Load older' }));
      expect(list).toHaveBeenLastCalledWith('c1', 's1', 'past', 20, 40);
    });

    it('shows the message of the server in a toast when the load fails, and keeps the list and the button', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'list').mockResolvedValue({
        ok: false,
        status: 429,
        message: 'Too many requests. Wait a minute.',
        errors: {},
      });
      renderList({ nextOffset: 20 });

      await userEvent.click(screen.getByRole('button', { name: 'Load more' }));

      expect(await screen.findByText('Too many requests. Wait a minute.')).toBeInTheDocument();
      expect(titles()).toEqual(['Weekly review', 'Workshop']);
      expect(screen.getByRole('button', { name: 'Load more' })).toBeInTheDocument();
      expect(status()).toBeEmptyDOMElement();
    });

    it('has no button when the first page holds everything', () => {
      renderList({ nextOffset: null });

      expect(screen.queryByRole('button', { name: /^Load/ })).toBeNull();
    });
  });

  describe('an empty list (rule 17)', () => {
    it('says that no event is planned, with "New event" for a reader who may create', () => {
      renderList({ events: [], canContribute: true });

      expect(screen.getByText('No upcoming events')).toBeInTheDocument();
      expect(screen.getByText('Events that are planned will show here.')).toBeInTheDocument();
      expect(screen.getAllByRole('link', { name: 'New event' })).toHaveLength(1);
      expect(screen.queryByRole('list', { name: 'Upcoming events' })).toBeNull();
      // The two links stay: the other list may hold events
      expect(screen.getByRole('link', { name: 'Past' })).toBeInTheDocument();
    });

    it('says that no event ended, without "New event" for a reader who may not create', () => {
      renderList({ events: [], scope: 'past' });

      expect(screen.getByText('No past events')).toBeInTheDocument();
      expect(screen.getByText('Events that ended will show here.')).toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'New event' })).toBeNull();
    });

    it('keeps its words in one place', () => {
      expect(EVENT_WORDS.emptyUpcomingTitle).toBe('No upcoming events');
      expect(EVENT_WORDS.emptyPastTitle).toBe('No past events');
      expect(EVENT_WORDS.loadMore).toBe('Load more');
      expect(EVENT_WORDS.loadOlder).toBe('Load older');
    });
  });

  describe('live updates (rule 18)', () => {
    const emit = (name: 'created' | 'updated' | 'deleted', eventCuid: string) =>
      act(() => emitSocketEvent(channel, `space.event.${name}`, { space_cuid: 's1', event_cuid: eventCuid, author_cuid: 'someone' }));

    it('gets a published event and inserts its card at its place: before a later start', async () => {
      const get = vi
        .spyOn(knowiiApiClient.communities.events, 'get')
        .mockResolvedValue({ ok: true, message: '', data: eventFixture('e9', 'Tomorrow', at('2026-11-12T16:00:00.000000Z')) });
      renderList();

      emit('created', 'e9');

      await waitFor(() => expect(titles()).toEqual(['Tomorrow', 'Weekly review', 'Workshop']));
      expect(get).toHaveBeenCalledWith('c1', 's1', 'e9');
    });

    it('keeps the drafts first when an event changes', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'get').mockResolvedValue({
        ok: true,
        message: '',
        data: eventFixture('e2', 'Workshop, moved', at('2026-11-12T16:00:00.000000Z')),
      });
      renderList({
        events: [
          eventFixture('e0', 'My draft', { state: 'draft', ...at('2026-12-20T16:00:00.000000Z') }),
          eventFixture('e1', 'Weekly review'),
          eventFixture('e2', 'Workshop', at('2026-11-20T16:00:00.000000Z')),
        ],
      });

      emit('updated', 'e2');

      await waitFor(() => expect(titles()).toEqual(['My draft', 'Workshop, moved', 'Weekly review']));
    });

    it('replaces the card of an event that changed, and shows its new state', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'get').mockResolvedValue({
        ok: true,
        message: '',
        data: eventFixture('e1', 'Weekly review', { state: 'cancelled' }),
      });
      renderList();

      emit('updated', 'e1');

      expect(await within(screen.getByRole('article', { name: 'Weekly review' })).findByText('Cancelled')).toBeInTheDocument();
      expect(titles()).toEqual(['Weekly review', 'Workshop']);
    });

    // The answer of the server for an event that the reader may not see (403) or that is gone (404)
    const gone = (status = 404) =>
      vi.spyOn(knowiiApiClient.communities.events, 'get').mockResolvedValue({ ok: false, status, message: 'No', errors: {} });

    it('keeps the card of an event that went back to draft for its host, the owner and an admin: it gets the event again', async () => {
      // "Back to draft" sends `space.event.deleted` on the space channel: the server still answers the draft to them
      const get = vi.spyOn(knowiiApiClient.communities.events, 'get').mockResolvedValue({
        ok: true,
        message: '',
        data: eventFixture('e2', 'Workshop', { state: 'draft', ...at('2026-11-20T16:00:00.000000Z') }),
      });
      renderList();
      expect(screen.queryByText('Draft')).toBeNull();

      emit('deleted', 'e2');

      await waitFor(() => expect(get).toHaveBeenCalledWith('c1', 's1', 'e2'));
      // The card stays, with its label, and goes first like every draft
      await waitFor(() => expect(titles()).toEqual(['Workshop', 'Weekly review']));
      const card = screen.getByRole('article', { name: 'Workshop' });
      expect(within(card).getByText('Draft')).toBeInTheDocument();
    });

    it.each([
      [403, 'went back to draft, for a reader who may not see a draft'],
      [404, 'was deleted'],
    ])('removes the card after the answer %i of the server: the event %s', async (status) => {
      const get = gone(status);
      renderList();

      emit('deleted', 'e1');
      // Not before the answer: the server decides
      expect(titles()).toEqual(['Weekly review', 'Workshop']);

      await waitFor(() => expect(titles()).toEqual(['Workshop']));
      expect(get).toHaveBeenCalledWith('c1', 's1', 'e1');
    });

    it.each([403, 404])('removes the card of an event that the reader may no longer see (%i)', async (status) => {
      gone(status);
      renderList();

      emit('updated', 'e2');

      await waitFor(() => expect(titles()).toEqual(['Weekly review']));
    });

    it('keeps the card when the request fails for another reason', async () => {
      const get = vi
        .spyOn(knowiiApiClient.communities.events, 'get')
        .mockResolvedValue({ ok: false, status: 500, message: 'No', errors: {} });
      renderList();

      emit('updated', 'e2');

      await waitFor(() => expect(get).toHaveBeenCalled());
      expect(titles()).toEqual(['Weekly review', 'Workshop']);
    });

    it('moves an event that ended out of "Upcoming"', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'get').mockResolvedValue({
        ok: true,
        message: '',
        data: eventFixture('e1', 'Weekly review', { is_past: true }),
      });
      renderList();
      emit('updated', 'e1');
      await waitFor(() => expect(titles()).toEqual(['Workshop']));
    });

    it('does not insert a new event that belongs to a page that is not loaded yet', async () => {
      const get = vi
        .spyOn(knowiiApiClient.communities.events, 'get')
        .mockResolvedValue({ ok: true, message: '', data: eventFixture('e9', 'Next year', at('2027-06-01T16:00:00.000000Z')) });
      renderList({ nextOffset: 20 });

      emit('created', 'e9');

      await waitFor(() => expect(get).toHaveBeenCalled());
      expect(titles()).toEqual(['Weekly review', 'Workshop']);
    });

    it('gives the focus of a card that goes away to the link of the next card', async () => {
      gone();
      renderList({ events: [eventFixture('e1', 'Weekly review'), eventFixture('e2', 'Workshop'), eventFixture('e3', 'Third')] });
      screen.getByRole('link', { name: 'Workshop' }).focus();

      emit('deleted', 'e2');

      await waitFor(() => expect(screen.getByRole('link', { name: 'Third' })).toHaveFocus());
      expect(titles()).toEqual(['Weekly review', 'Third']);
    });

    it('gives the focus of the last card to the card before, and of the only card to the link of the open list', async () => {
      gone();
      renderList();
      screen.getByRole('link', { name: 'Workshop' }).focus();

      emit('deleted', 'e2');
      await waitFor(() => expect(screen.getByRole('link', { name: 'Weekly review' })).toHaveFocus());

      emit('deleted', 'e1');
      await waitFor(() => expect(screen.getByRole('link', { name: 'Upcoming' })).toHaveFocus());
      expect(screen.getByText('No upcoming events')).toBeInTheDocument();
    });

    it('leaves the focus alone when it is not in the card that goes away', async () => {
      gone();
      renderList();
      screen.getByRole('link', { name: 'Weekly review' }).focus();

      emit('deleted', 'e2');

      await waitFor(() => expect(titles()).toEqual(['Weekly review']));
      expect(screen.getByRole('link', { name: 'Weekly review' })).toHaveFocus();
    });

    it('shows a new event in an empty list', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'get').mockResolvedValue({
        ok: true,
        message: '',
        data: eventFixture('e9', 'The first one'),
      });
      renderList({ events: [] });

      emit('created', 'e9');

      expect(await screen.findByRole('link', { name: 'The first one' })).toBeInTheDocument();
      expect(screen.queryByText('No upcoming events')).toBeNull();
    });

    it('never listens for a visitor: the page is static (ADR 0044 point 13)', () => {
      const get = vi.spyOn(knowiiApiClient.communities.events, 'get');
      renderList({ visitor: true });

      emit('created', 'e9');
      emit('deleted', 'e1');

      expect(get).not.toHaveBeenCalled();
      expect(titles()).toEqual(['Weekly review', 'Workshop']);
    });
  });

  describe('the labels of the answer and of the places (#1515, rules 29 to 33)', () => {
    const card = (title: string) => screen.getByRole('article', { name: title });
    const labels = (title: string) =>
      Array.from(card(title).querySelectorAll('span.rounded-md.border.text-xs')).map((badge) => badge.textContent);

    it('writes the answer of the member on its card, after the state, on every event that they answered (rules 29 and 30)', () => {
      renderList({
        events: [
          eventFixture('e1', 'Said yes', { my_answer: 'yes' }),
          eventFixture('e2', 'Said replay', { my_answer: 'replay', replay_expected: true }),
          eventFixture('e3', 'Said no', { my_answer: 'no' }),
          eventFixture('e4', 'Called off', { state: 'cancelled', can_answer: false, my_answer: 'yes' }),
          eventFixture('e5', 'Not answered'),
        ],
      });

      expect(labels('Said yes')).toEqual(['You said Yes']);
      expect(labels('Said replay')).toEqual(['You said Replay only']);
      expect(labels('Said no')).toEqual(['You said No']);
      // The state first, then the answer: a cancelled event keeps it
      expect(labels('Called off')).toEqual(['Cancelled', 'You said Yes']);
      expect(labels('Not answered')).toEqual([]);
      // "You said Yes" has a check icon that a screen reader skips: the words carry the meaning
      const yes = within(card('Said yes')).getByText('You said Yes').closest('span.rounded-md.border.text-xs');
      expect(yes?.querySelector('svg')).not.toBeNull();
    });

    it('keeps the answer on a past event', () => {
      renderList({
        scope: 'past',
        events: [eventFixture('e1', 'Last week', { is_past: true, can_answer: false, my_answer: 'replay', places_left: 3 })],
      });

      // An event that is over shows no places
      expect(labels('Last week')).toEqual(['You said Replay only']);
    });

    it('writes the places that are left when the event has a limit, in the neutral color for every number (rule 31)', () => {
      renderList({
        events: [
          eventFixture('e1', 'Many places', { places_left: 28 }),
          eventFixture('e2', 'One place', { places_left: 1 }),
          eventFixture('e3', 'No place', { places_left: 0, my_answer: 'yes' }),
          eventFixture('e4', 'No limit', { places_left: null }),
          eventFixture('e5', 'Called off', { state: 'cancelled', can_answer: false, places_left: 5 }),
          eventFixture('e6', 'A draft', { state: 'draft', can_answer: false, places_left: 5 }),
        ],
      });

      expect(labels('Many places')).toEqual(['28 places left']);
      expect(labels('One place')).toEqual(['1 place left']);
      expect(labels('No place')).toEqual(['You said Yes', 'Full']);
      expect(labels('No limit')).toEqual([]);
      // Only a published event that is not over takes answers
      expect(labels('Called off')).toEqual(['Cancelled']);
      expect(labels('A draft')).toEqual(['Draft']);
      const classes = ['28 places left', '1 place left', 'Full'].map(
        (words) => screen.getByText(words).closest('span.rounded-md.border.text-xs')?.className,
      );
      expect(new Set(classes).size).toBe(1);
      expect(classes[0]).toContain('border-border-strong');
    });

    it('shows no label to a visitor: the server gives them no answer and no places (rule 32)', () => {
      const answers = [
        vi.spyOn(knowiiApiClient.communities.events.answer, 'get'),
        vi.spyOn(knowiiApiClient.communities.events.answer, 'set'),
        vi.spyOn(knowiiApiClient.communities.events.answer, 'clear'),
        vi.spyOn(knowiiApiClient.communities.events.answers, 'list'),
      ];
      renderList({
        visitor: true,
        events: [eventFixture('e1', 'Weekly review', { my_answer: null, can_answer: false, places_left: null, replay_expected: true })],
      });

      expect(labels('Weekly review')).toEqual([]);
      // No request of the answers for a visitor (rule 61)
      for (const call of answers) {
        expect(call).not.toHaveBeenCalled();
      }
    });

    it('has no answer control on a card: a member answers on the page of the event (rule 33)', () => {
      renderList({ events: [eventFixture('e1', 'Weekly review', { places_left: 28, replay_expected: true })] });

      expect(within(card('Weekly review')).queryByRole('radio')).toBeNull();
      expect(within(card('Weekly review')).queryByRole('button')).toBeNull();
      expect(within(card('Weekly review')).getAllByRole('link')).toHaveLength(1);
    });

    it('gets the event again when its places changed, and its card says the new number (rule 57)', async () => {
      const get = vi
        .spyOn(knowiiApiClient.communities.events, 'get')
        .mockResolvedValueOnce({ ok: true, message: '', data: eventFixture('e1', 'Weekly review', { places_left: 27 }) })
        .mockResolvedValueOnce({ ok: true, message: '', data: eventFixture('e1', 'Weekly review', { places_left: 0 }) });
      renderList({
        events: [
          eventFixture('e1', 'Weekly review', { places_left: 28 }),
          eventFixture('e2', 'Workshop', at('2026-11-20T16:00:00.000000Z')),
        ],
      });
      // The signal names the event only: no member
      const places = () =>
        act(() => emitSocketEvent(channel, 'space.event.answers.changed', { space_cuid: 's1', event_cuid: 'e1', author_cuid: null }));

      places();
      expect(await within(card('Weekly review')).findByText('27 places left')).toBeInTheDocument();
      expect(get).toHaveBeenCalledWith('c1', 's1', 'e1');

      places();
      expect(await within(card('Weekly review')).findByText('Full')).toBeInTheDocument();
      expect(titles()).toEqual(['Weekly review', 'Workshop']);
      expect(get).toHaveBeenCalledTimes(2);
    });

    it('does not listen to the places for a visitor', () => {
      const get = vi.spyOn(knowiiApiClient.communities.events, 'get');
      renderList({ visitor: true });

      act(() => emitSocketEvent(channel, 'space.event.answers.changed', { space_cuid: 's1', event_cuid: 'e1', author_cuid: null }));

      expect(get).not.toHaveBeenCalled();
    });

    it('has no automated violation with the labels (axe)', async () => {
      const { container } = renderList({
        events: [
          eventFixture('e1', 'Said yes', { my_answer: 'yes', places_left: 0 }),
          eventFixture('e2', 'Said replay', { my_answer: 'replay', replay_expected: true, places_left: 28 }),
          eventFixture('e3', 'Called off', { state: 'cancelled', can_answer: false, my_answer: 'no' }),
        ],
      });

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  });

  describe('accessibility (design 11.6)', () => {
    it.each(['a member', 'a visitor'])('has no automated violation for %s (axe)', async (reader) => {
      const { container } = renderList({
        visitor: reader === 'a visitor',
        canContribute: reader === 'a member',
        nextOffset: 20,
        events: [
          eventFixture('e1', 'A draft', { state: 'draft' }),
          eventFixture('e2', 'Called off', { state: 'cancelled', host: null }),
          eventFixture('e3', 'With a cover', {
            cover: {
              cuid: 'up1',
              url: '/uploads/cover.webp',
              mime_type: 'image/webp',
              width: 1600,
              height: 900,
              size_bytes: 1000,
              alt: 'x',
            },
            cover_alt: 'The team at a table',
          }),
        ],
      });

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });

    it('has no automated violation when the list is empty (axe)', async () => {
      const { container } = renderList({ events: [], canContribute: true, scope: 'past' });

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  });
});
