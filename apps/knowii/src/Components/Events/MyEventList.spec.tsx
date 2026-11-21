import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { EventScope, SpaceEvent } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import type { ToolkitLinkProps } from '@/Components/ui';
import { AccountTimeZoneProvider, Toaster, ToolkitProvider } from '@/Components/ui';
import { EVENT_TEST_NOW, eventFixture, eventsPagination, eventsRoute } from '@/test-utils/events';
import { EVENT_WORDS } from './event-words';
import { MyEventList } from './MyEventList';

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { auth: { user: { cuid: 'me' } } } }),
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

function ToolkitLink({ href, children, className, 'aria-current': ariaCurrent }: ToolkitLinkProps) {
  return (
    <a href={href} className={className} aria-current={ariaCurrent}>
      {children}
    </a>
  );
}

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
  // jsdom has no scrollIntoView: the first new row scrolls into view after a load
  Element.prototype.scrollIntoView = vi.fn();
});

const me = { cuid: 'me', name: 'Mia Member', username: 'mia', avatar_url: null };
const second = { cuid: 'c2', slug: 'second-community', name: 'Second community' };
const at = (iso: string) => ({ starts_at: iso, ends_at: new Date(new Date(iso).getTime() + 3_600_000).toISOString() });

/**
 * One row of each kind: an own draft, a Yes, a Replay only of another community, a hosted event, a cancelled event with a
 * Yes, an event that the reader hosts and answered, and one that happens now
 */
function rowsOfEachKind(): SpaceEvent[] {
  return [
    eventFixture('d1', 'My draft', { state: 'draft', host: me }),
    eventFixture('y1', 'Weekly review', { my_answer: 'yes' }),
    eventFixture('r1', 'Replay session', {
      my_answer: 'replay',
      community: second,
      space: { cuid: 's2', slug: 'study-hours', name: 'Study hours' },
    }),
    eventFixture('h1', 'I host this', { host: me }),
    eventFixture('c1', 'Called off', { state: 'cancelled', my_answer: 'yes' }),
    eventFixture('hy', 'I host and go', { host: me, my_answer: 'yes' }),
    eventFixture('n1', 'Happening', { my_answer: 'yes', ...at('2026-11-11T08:30:00.000000Z') }),
  ];
}

interface Options {
  scope?: EventScope;
  events?: SpaceEvent[];
  nextOffset?: number | null;
  total?: number;
  showCommunity?: boolean;
}

function tree({ scope = 'upcoming', events, nextOffset = null, total, showCommunity = true }: Options = {}) {
  const items = events ?? rowsOfEachKind();
  return (
    <AppDataProvider>
      <AccountTimeZoneProvider value="America/New_York">
        <ToolkitProvider linkComponent={ToolkitLink}>
          <main>
            <MyEventList
              initialEvents={{ items, pagination: eventsPagination(nextOffset, total ?? items.length) }}
              scope={scope}
              showCommunity={showCommunity}
              readerCuid="me"
            />
          </main>
        </ToolkitProvider>
        <Toaster />
      </AccountTimeZoneProvider>
    </AppDataProvider>
  );
}

const rowOf = (title: string) => screen.getByRole('article', { name: title });
// The items of the list of events (the two links are items of their own list)
const rows = () => within(screen.getByRole('list', { name: /events$/ })).getAllByRole('listitem');
const listStatus = () =>
  within(screen.getByRole('region', { name: EVENT_WORDS.myEvents }))
    .getAllByRole('status')
    .find((element) => element.tagName === 'P') as HTMLElement;

describe('the list of "My events" (#1515, design 6.8 and 11.3)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(EVENT_TEST_NOW);
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('has the links "Upcoming" and "Past" in a navigation named "My events", the open one marked', async () => {
    const { container, rerender } = render(tree());

    const navigation = screen.getByRole('navigation', { name: EVENT_WORDS.myEvents });
    expect(within(navigation).getByRole('link', { name: EVENT_WORDS.upcoming })).toHaveAttribute('href', '/events');
    expect(within(navigation).getByRole('link', { name: EVENT_WORDS.upcoming })).toHaveAttribute('aria-current', 'page');
    expect(within(navigation).getByRole('link', { name: EVENT_WORDS.past })).toHaveAttribute('href', '/events?scope=past');
    expect(within(navigation).getByRole('link', { name: EVENT_WORDS.past })).not.toHaveAttribute('aria-current');
    expect((await axe.run(container)).violations).toEqual([]);

    rerender(tree({ scope: 'past', events: [eventFixture('p1', 'Last week', { is_past: true, my_answer: 'yes' })] }));
    expect(within(navigation).getByRole('link', { name: EVENT_WORDS.past })).toHaveAttribute('aria-current', 'page');
    // The status says which list opened and how many events it holds
    expect(listStatus()).toHaveTextContent('Past events: 1');
    expect(screen.getByRole('list', { name: EVENT_WORDS.pastList })).toBeInTheDocument();
  });

  it('shows a real list of rows, each with the labels of its kind as text, and "Add to calendar" on every row that is not a draft', async () => {
    const { container } = render(tree());

    const list = screen.getByRole('list', { name: EVENT_WORDS.upcomingList });
    expect(within(list).getAllByRole('listitem')).toHaveLength(7);

    const draft = rowOf('My draft');
    expect(within(draft).getByText(EVENT_WORDS.draft)).toBeInTheDocument();
    expect(within(draft).getByText(EVENT_WORDS.youHost)).toBeInTheDocument();
    expect(within(draft).queryByRole('link', { name: /Add to calendar/ })).toBeNull();

    expect(within(rowOf('Weekly review')).getByText('You said Yes')).toBeInTheDocument();
    expect(within(rowOf('Weekly review')).queryByText(EVENT_WORDS.youHost)).toBeNull();
    expect(within(rowOf('Replay session')).getByText('You said Replay only')).toBeInTheDocument();
    expect(within(rowOf('I host this')).getByText(EVENT_WORDS.youHost)).toBeInTheDocument();
    expect(within(rowOf('Called off')).getByText(EVENT_WORDS.cancelled)).toBeInTheDocument();
    expect(within(rowOf('Called off')).getByText('You said Yes')).toBeInTheDocument();
    expect(within(rowOf('Called off')).getByRole('link', { name: 'Add to calendar: Called off' })).toBeInTheDocument();
    expect(within(rowOf('Happening')).getByText(EVENT_WORDS.happeningNow)).toBeInTheDocument();
    // An event that the reader hosts and answered shows both, "You host" first
    const both = within(rowOf('I host and go'));
    expect(both.getByText(EVENT_WORDS.youHost)).toBeInTheDocument();
    expect(both.getByText('You said Yes')).toBeInTheDocument();
    expect(
      Boolean(
        both.getByText(EVENT_WORDS.youHost).compareDocumentPosition(both.getByText('You said Yes')) & Node.DOCUMENT_POSITION_FOLLOWING,
      ),
    ).toBe(true);

    // The link of each row that is not a draft: the address of its file, and the title in its name
    const link = within(rowOf('Replay session')).getByRole('link', { name: 'Add to calendar: Replay session' });
    expect(link).toHaveAttribute('href', '/api/v1/communities/c2/spaces/s2/events/r1/calendar-file');
    expect(link).toHaveAttribute('download');
    expect(screen.getAllByRole('link', { name: /^Add to calendar: / })).toHaveLength(6);

    // The title is the link to the event page, in its own community and space; no row has a cover
    expect(within(rowOf('Replay session')).getByRole('link', { name: 'Replay session' })).toHaveAttribute(
      'href',
      '/community/second-community/space/study-hours/event/replay-session',
    );
    expect(container.querySelector('img')).toBeNull();
    // The time in the short form, with the long form for a screen reader, in the zone of the account
    expect(within(rowOf('Weekly review')).getByText('Wed 18 Nov, 11:00 Eastern Standard Time')).toBeInTheDocument();
    expect(within(rowOf('Weekly review')).getByText(/^Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time/)).toHaveClass('sr-only');
    expect((await axe.run(container)).violations).toEqual([]);
  });

  it('names the community and the space of each row for a member of several communities, and the space alone otherwise', () => {
    const { rerender } = render(tree());

    expect(within(rowOf('Weekly review')).getByText('Tests, Office hours')).toBeInTheDocument();
    expect(within(rowOf('Replay session')).getByText('Second community, Study hours')).toBeInTheDocument();

    rerender(tree({ showCommunity: false }));
    expect(within(rowOf('Weekly review')).getByText('Office hours')).toBeInTheDocument();
    expect(screen.queryByText(/^Tests, /)).toBeNull();
    // Text, not a link
    expect(within(rowOf('Weekly review')).queryByRole('link', { name: 'Office hours' })).toBeNull();
  });

  it('sends no request when it renders', () => {
    const list = vi.spyOn(knowiiApiClient.users.events, 'list');
    const fetchSpy = vi.spyOn(globalThis, 'fetch');
    render(tree({ nextOffset: 20, total: 45 }));

    expect(list).not.toHaveBeenCalled();
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('loads 45 events over three pages: one request per press, the focus on the first new row, and the number in the status', async () => {
    const page = (from: number, count: number) =>
      Array.from({ length: count }, (_, index) => eventFixture(`e${from + index}`, `Event ${from + index}`, { my_answer: 'yes' }));
    const list = vi
      .spyOn(knowiiApiClient.users.events, 'list')
      .mockResolvedValueOnce({ ok: true, message: '', data: { items: page(21, 20), pagination: eventsPagination(40, 45) } })
      .mockResolvedValueOnce({ ok: true, message: '', data: { items: page(41, 5), pagination: eventsPagination(null, 45) } });
    const user = userEvent.setup();
    const { container } = render(tree({ events: page(1, 20), nextOffset: 20, total: 45 }));

    await user.click(screen.getByRole('button', { name: EVENT_WORDS.loadMore }));

    expect(list).toHaveBeenNthCalledWith(1, 'upcoming', 20, 20);
    await waitFor(() => expect(screen.getByRole('link', { name: 'Event 21' })).toHaveFocus());
    expect(listStatus()).toHaveTextContent('20 more loaded');
    expect(rows()).toHaveLength(40);

    await user.click(screen.getByRole('button', { name: EVENT_WORDS.loadMore }));

    expect(list).toHaveBeenNthCalledWith(2, 'upcoming', 20, 40);
    await waitFor(() => expect(screen.getByRole('link', { name: 'Event 41' })).toHaveFocus());
    expect(listStatus()).toHaveTextContent('5 more loaded');
    expect(rows()).toHaveLength(45);
    // The last page: no button
    expect(screen.queryByRole('button', { name: EVENT_WORDS.loadMore })).toBeNull();
    expect(list).toHaveBeenCalledTimes(2);
    expect((await axe.run(container)).violations).toEqual([]);
  });

  it('says "Load older" on the past list, and never adds an event a second time', async () => {
    const shown = eventFixture('p1', 'Last week', { is_past: true, my_answer: 'yes' });
    vi.spyOn(knowiiApiClient.users.events, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: {
        items: [shown, eventFixture('p2', 'Two weeks ago', { is_past: true, my_answer: 'replay' })],
        pagination: eventsPagination(null, 2),
      },
    });
    const user = userEvent.setup();
    render(tree({ scope: 'past', events: [shown], nextOffset: 1, total: 2 }));

    await user.click(screen.getByRole('button', { name: EVENT_WORDS.loadOlder }));

    await waitFor(() => expect(screen.getByRole('link', { name: 'Two weeks ago' })).toHaveFocus());
    expect(rows()).toHaveLength(2);
    expect(listStatus()).toHaveTextContent('1 more loaded');
  });

  it('shows the message of the server when a load fails, and keeps the rows and the button', async () => {
    vi.spyOn(knowiiApiClient.users.events, 'list').mockResolvedValue({ ok: false, status: 429, message: 'Too many requests', errors: {} });
    const user = userEvent.setup();
    render(tree({ nextOffset: 7, total: 10 }));

    await user.click(screen.getByRole('button', { name: EVENT_WORDS.loadMore }));

    expect(await screen.findByText('Too many requests')).toBeInTheDocument();
    expect(rows()).toHaveLength(7);
    expect(screen.getByRole('button', { name: EVENT_WORDS.loadMore })).toBeInTheDocument();
  });

  it('shows the two empty states, without an action', async () => {
    const { container, rerender } = render(tree({ events: [] }));

    expect(screen.getByText(EVENT_WORDS.emptyUpcomingTitle, { ignore: '[role=status]' })).toBeInTheDocument();
    expect(screen.getByText(EVENT_WORDS.myEventsEmptyUpcomingDescription)).toBeInTheDocument();
    expect(screen.queryByRole('list', { name: EVENT_WORDS.upcomingList })).toBeNull();
    expect(screen.queryByRole('button')).toBeNull();
    expect((await axe.run(container)).violations).toEqual([]);

    rerender(tree({ scope: 'past', events: [] }));
    expect(screen.getByText(EVENT_WORDS.emptyPastTitle, { ignore: '[role=status]' })).toBeInTheDocument();
    expect(screen.getByText(EVENT_WORDS.myEventsEmptyPastDescription)).toBeInTheDocument();
    expect(listStatus()).toHaveTextContent(EVENT_WORDS.emptyPastTitle);
    expect((await axe.run(container)).violations).toEqual([]);
  });
});
