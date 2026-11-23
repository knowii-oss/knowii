import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { Comment, Community, EventPermissions, SpaceEvent } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { EVENT_TEST_NOW, eventFixture, eventsCommunity, eventsRoute, eventsSpace } from '@/test-utils/events';
import { Toaster } from '@/Components/ui';
import EventPage from './Show';

// An address with a script scheme, in two parts: it must never become a link
const NOT_AN_ADDRESS = ['java', 'script:alert(1)'].join('');

const shared = vi.hoisted(() => ({
  user: null as { cuid: string; time_zone: string | null } | null,
  reload: vi.fn(),
  visit: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
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
  Head: () => null,
  router: { reload: shared.reload, visit: shared.visit },
  usePage: () => ({ props: { auth: { user: shared.user } }, url: '/' }),
}));
// The layout gives the two things that the page reads from it: whether the reader is a visitor, and the zone of the account
vi.mock('@/Layouts/AppLayout', async () => {
  const { VisitorProvider } = await import('@/Components/Reader/Reader');
  const { AccountTimeZoneProvider } = await import('@/Components/ui');
  return {
    default: ({ children }: { children: React.ReactNode }) => (
      <VisitorProvider value={shared.user === null}>
        <AccountTimeZoneProvider value={shared.user?.time_zone ?? null}>
          <main>{children}</main>
        </AccountTimeZoneProvider>
      </VisitorProvider>
    ),
  };
});
vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
});

const NONE: EventPermissions = {
  canUpdate: false,
  canCancel: false,
  canDelete: false,
  canDuplicate: false,
  canViewAnswers: false,
  canComment: false,
  canModerate: false,
  mustCancelBeforeDelete: false,
};
// The host, the owner and the admins: they also read the answers (#1515)
const ALL: EventPermissions = {
  canUpdate: true,
  canCancel: true,
  canDelete: true,
  canDuplicate: true,
  canViewAnswers: true,
  canComment: true,
  canModerate: true,
  mustCancelBeforeDelete: false,
};
// A moderator with the right to delete content: cancels and deletes the events of others, does not edit them, and does
// not read the answers
const MODERATOR: EventPermissions = {
  canUpdate: false,
  canCancel: true,
  canDelete: true,
  canDuplicate: false,
  canViewAnswers: false,
  canComment: true,
  canModerate: true,
  mustCancelBeforeDelete: false,
};

// What a load asks of the router (#1515): the event alone, or every prop. Each load also gives its callbacks (the cancel
// function, the end), so the options are never the bare object
const EVENT_ALONE = expect.objectContaining({ only: ['event'] });
const WHOLE_PAGE = expect.not.objectContaining({ only: expect.anything() });

type Reader = 'member' | 'outsider' | 'visitor';

interface Options {
  reader?: Reader;
  zone?: string | null;
  permissions?: EventPermissions;
  comments?: Comment[];
}

function renderPage(event: SpaceEvent, { reader = 'member', zone = 'America/New_York', permissions = NONE, comments = [] }: Options = {}) {
  shared.user = reader === 'visitor' ? null : { cuid: 'me', time_zone: zone };
  const community = { ...eventsCommunity, is_member: reader === 'member' } as Community;
  return render(
    <AppDataProvider>
      <EventPage community={community} space={eventsSpace} spaceGroups={[]} event={event} comments={comments} permissions={permissions} />
      <Toaster />
    </AppDataProvider>,
  );
}

const withBody = (changes: Record<string, unknown> = {}) =>
  eventFixture('e1', 'Weekly review', { body: 'Bring **questions**', body_html: '<p>Bring <strong>questions</strong></p>', ...changes });
const openMenu = async () => userEvent.click(screen.getByRole('button', { name: 'Event actions' }));
const menuEntries = () => screen.queryAllByRole('menuitem').map((entry) => entry.textContent);

describe('the page of an event (#1514, design 6.2)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(EVENT_TEST_NOW);
    shared.reload.mockClear();
    shared.visit.mockClear();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  describe('the content, in its order (rule 19)', () => {
    it('shows the title as the only h1, then the cover, the date, the host, the meeting and the description', () => {
      const cover = {
        cuid: 'up1',
        url: '/uploads/cover.webp',
        mime_type: 'image/webp',
        width: 1600,
        height: 900,
        size_bytes: 1000,
        alt: 'x',
      };
      renderPage(withBody({ cover, cover_alt: 'The team at a table' }));

      expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
      const title = screen.getByRole('heading', { level: 1, name: 'Weekly review' });
      const image = screen.getByRole('img', { name: 'The team at a table' });
      const time = screen.getByText('Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time');
      const host = screen.getByRole('heading', { level: 2, name: 'Host' });
      const meeting = screen.getByRole('heading', { level: 2, name: 'Meeting' });
      const description = screen.getByText('questions');
      const order = [title, image, time, host, meeting, description];
      for (let index = 1; index < order.length; index++) {
        const before = order[index - 1] as HTMLElement;
        const after = order[index] as HTMLElement;
        expect(before.compareDocumentPosition(after) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      }
      expect(image).toHaveAttribute('src', '/uploads/cover.webp');
      // The description is the HTML that the server rendered
      expect(description.tagName).toBe('STRONG');
    });

    it('shows no cover box without a cover, a decorative cover without a description, and no description block without a text', () => {
      const cover = {
        cuid: 'up1',
        url: '/uploads/cover.webp',
        mime_type: 'image/webp',
        width: 1600,
        height: 900,
        size_bytes: 1000,
        alt: 'x',
      };
      const { unmount } = renderPage(eventFixture('e1', 'Weekly review'));
      expect(document.querySelector('img')).toBeNull();
      expect(screen.queryByRole('heading', { name: 'Description' })).toBeNull();
      unmount();

      renderPage(eventFixture('e1', 'Weekly review', { cover, cover_alt: null }));
      expect(document.querySelector('img[src="/uploads/cover.webp"]')).toHaveAttribute('alt', '');
    });
  });

  describe('the comments (#1516, design 6.2 point 2)', () => {
    const at = new Date(EVENT_TEST_NOW);
    const thread: Comment[] = [
      {
        cuid: 'k1',
        body: 'See you there',
        body_html: '<p>See you there</p>',
        edited_at: null,
        deleted_at: null,
        author: { cuid: 'ada', name: 'Ada', username: 'ada' },
        parent_cuid: null,
        replies: [],
        created_at: at,
        updated_at: at,
      },
    ];

    it('come last, after the article, with the form for a member who can comment', async () => {
      const { container } = renderPage(withBody(), { comments: thread, permissions: { ...NONE, canComment: true } });

      const article = container.querySelector('article') as HTMLElement;
      const heading = screen.getByRole('heading', { name: '1 comment' });
      expect(article.contains(heading)).toBe(false);
      expect(article.compareDocumentPosition(heading) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(screen.getByText('See you there')).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: 'Write a comment' })).toBeInTheDocument();
      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
      expect(results.violations).toEqual([]);
    });

    it('follow the description and the section after the event, in the order of the design (6.2 point 2)', async () => {
      vi.setSystemTime(new Date('2026-11-19T09:00:00Z'));
      const { container } = renderPage(
        withBody({ is_past: true, notes: 'The slides', notes_html: '<p>The slides of the session</p>', replay_expected: true }),
        { comments: thread, permissions: { ...NONE, canComment: true } },
      );

      const description = screen.getByText('questions');
      const after = screen.getByRole('heading', { level: 2, name: 'After the event' });
      const notes = screen.getByText('The slides of the session');
      const comments = screen.getByRole('heading', { name: '1 comment' });
      const order = [description, after, notes, comments];
      for (let index = 1; index < order.length; index++) {
        expect(
          (order[index - 1] as HTMLElement).compareDocumentPosition(order[index] as HTMLElement) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
      }
      expect((container.querySelector('article') as HTMLElement).contains(after)).toBe(true);
      expect(screen.getByText('The host plans a replay. It shows here when the host adds it.')).toBeInTheDocument();
      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
      expect(results.violations).toEqual([]);
    });

    it('show a visitor the comments with the prompt to sign in, and no form', async () => {
      const { container } = renderPage(withBody(), { reader: 'visitor', comments: thread });

      expect(screen.getByText('See you there')).toBeInTheDocument();
      expect(screen.queryByRole('textbox', { name: 'Write a comment' })).toBeNull();
      expect(screen.getAllByRole('link', { name: /sign in/i }).length).toBeGreaterThan(0);
      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
      expect(results.violations).toEqual([]);
    });

    it('are not on the page of a draft', () => {
      renderPage(withBody({ state: 'draft' }), { permissions: ALL });

      expect(screen.queryByRole('heading', { name: /comment/i })).toBeNull();
      expect(screen.queryByRole('textbox', { name: 'Write a comment' })).toBeNull();
    });
  });

  describe('"Add to calendar" (#1515, E2c rules 27 to 33)', () => {
    it('comes right after the time and before the host, as a link to the calendar file with its words as its name', async () => {
      const { container } = renderPage(withBody());

      const time = screen.getByText('Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time');
      const link = screen.getByRole('link', { name: 'Add to calendar' });
      const host = screen.getByRole('heading', { level: 2, name: 'Host' });
      expect(time.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(link.compareDocumentPosition(host) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(link).toHaveAttribute('href', '/api/v1/communities/c1/spaces/s1/events/e1/calendar-file');
      expect(link).toHaveAttribute('download');
      expect((await axe.run(container)).violations).toEqual([]);
    });

    it('stays for a member on a cancelled event and on an event that ended', () => {
      const { unmount } = renderPage(withBody({ state: 'cancelled', can_answer: false }));
      expect(screen.getByRole('link', { name: 'Add to calendar' })).toBeInTheDocument();
      unmount();

      renderPage(
        withBody({ is_past: true, can_answer: false, starts_at: '2026-11-03T16:00:00.000000Z', ends_at: '2026-11-03T17:00:00.000000Z' }),
      );
      expect(screen.getByRole('link', { name: 'Add to calendar' })).toBeInTheDocument();
    });

    it('is not there for a visitor, for a person who is not a member, and for every reader of a draft', () => {
      for (const reader of ['visitor', 'outsider'] as const) {
        const { unmount } = renderPage(withBody({ can_view_location: false, meeting_url: null, can_answer: false }), { reader });
        expect(screen.queryByRole('link', { name: 'Add to calendar' })).toBeNull();
        expect(screen.queryByText('Add to calendar')).toBeNull();
        unmount();
      }

      renderPage(withBody({ state: 'draft', can_answer: false }), { permissions: ALL });
      expect(screen.queryByRole('link', { name: 'Add to calendar' })).toBeNull();
    });
  });

  describe('the times (rules 10, 11 and 12)', () => {
    it('is a time element with the UTC start, in the zone of the account, with the time of the host when it reads differently', () => {
      renderPage(withBody());

      const time = screen.getByText('Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time');
      expect(time.tagName).toBe('TIME');
      expect(time).toHaveAttribute('datetime', '2026-11-18T16:00:00.000Z');
      expect(time).not.toHaveAttribute('title');
      // The time of the host is a time element too, with the same UTC start (rule 11)
      const hostTime = screen.getByText('Wednesday 18 November, 17:00 Central European Standard Time');
      expect(hostTime.tagName).toBe('TIME');
      expect(hostTime).toHaveAttribute('datetime', '2026-11-18T16:00:00.000Z');
      expect(hostTime).not.toHaveAttribute('title');
      expect(hostTime.parentElement).toHaveTextContent("The host's time: Wednesday 18 November, 17:00 Central European Standard Time");
      expect(document.querySelectorAll('time')).toHaveLength(2);
    });

    it('shows no time of the host when the reader reads the same clock', () => {
      renderPage(withBody(), { zone: 'Europe/Paris' });

      expect(screen.getByText('Wednesday 18 November, 17:00 to 18:00 Central European Standard Time')).toBeInTheDocument();
      expect(screen.queryByText(/The host's time/)).toBeNull();
    });

    it('writes the day of the reader: Thursday in Singapore', () => {
      renderPage(withBody(), { zone: 'Asia/Singapore' });

      expect(screen.getByText('Thursday 19 November, 00:00 to 01:00 Singapore Standard Time')).toBeInTheDocument();
      expect(screen.getByText("The host's time:")).toHaveTextContent(
        "The host's time: Wednesday 18 November, 17:00 Central European Standard Time",
      );
    });

    it('uses the zone of the browser for a visitor', () => {
      renderPage(withBody(), { reader: 'visitor' });
      const browser = Intl.DateTimeFormat().resolvedOptions().timeZone;
      const zoneName = new Intl.DateTimeFormat('en-GB', { timeZone: browser, timeZoneName: 'long' })
        .formatToParts(new Date('2026-11-18T16:00:00Z'))
        .find((part) => part.type === 'timeZoneName')?.value;

      expect(document.querySelector('time')?.textContent).toContain(zoneName);
    });
  });

  describe('the state line (rule 20)', () => {
    const stateOf = () => document.querySelector('[data-slot="event-state"]')?.textContent ?? '';

    it('says who sees a draft', () => {
      renderPage(withBody({ state: 'draft' }), { permissions: ALL });

      expect(screen.getByText('Draft. Only the host, the owner and the admins see it.')).toBeInTheDocument();
    });

    it('shows the banner "Cancelled" for a cancelled event', () => {
      renderPage(withBody({ state: 'cancelled' }));

      expect(stateOf()).toBe('Cancelled');
    });

    it('says "Happening now" between the start and the end of a published event', () => {
      const started = new Date(EVENT_TEST_NOW.getTime() - 600_000).toISOString();
      const ends = new Date(EVENT_TEST_NOW.getTime() + 600_000).toISOString();
      renderPage(withBody({ starts_at: started, ends_at: ends }));

      expect(stateOf()).toBe('Happening now');
    });

    it('says "This event is over" for a past event, also under the banner of a cancelled one', () => {
      const { unmount } = renderPage(
        withBody({ is_past: true, starts_at: '2026-11-01T16:00:00.000000Z', ends_at: '2026-11-01T17:00:00.000000Z' }),
      );
      expect(stateOf()).toBe('This event is over');
      unmount();

      renderPage(
        withBody({ is_past: true, state: 'cancelled', starts_at: '2026-11-01T16:00:00.000000Z', ends_at: '2026-11-01T17:00:00.000000Z' }),
      );
      expect(stateOf()).toBe('CancelledThis event is over');
    });

    it('shows no state for a published event that is still to come', () => {
      renderPage(withBody());

      expect(document.querySelector('[data-slot="event-state"]')).toBeNull();
    });
  });

  describe('the host (rule 19)', () => {
    it('links the name to the member page for a member, with the username', () => {
      renderPage(withBody());

      expect(screen.getByRole('link', { name: 'Ada Lovelace' })).toHaveAttribute('href', '/community/tests/member/ada');
      expect(screen.getByText('ada')).toBeInTheDocument();
    });

    it.each(['visitor', 'outsider'] as const)('writes the name as text for a %s: the member page is for members', (reader) => {
      renderPage(withBody({ can_view_location: false, meeting_url: null }), { reader });

      expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
      expect(screen.queryByRole('link', { name: 'Ada Lovelace' })).toBeNull();
    });

    it('says "Deleted member" when the account of the host is gone', () => {
      renderPage(withBody({ host: null }));

      expect(screen.getByText('Deleted member')).toBeInTheDocument();
    });
  });

  describe('the meeting block (rule 21)', () => {
    const block = () => screen.getByRole('region', { name: 'Meeting' });

    it('gives a member the link "Join the meeting", which opens in a new tab and says so', () => {
      renderPage(withBody({ place: 'Room 2, Main street 1' }));

      const link = within(block()).getByRole('link', { name: 'Join the meeting (Opens in a new tab)' });
      expect(link).toHaveAttribute('href', 'https://meet.example.com/abc');
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
      expect(within(block()).getByText('Room 2, Main street 1')).toBeInTheDocument();
    });

    it.each([
      ['cancelled', { state: 'cancelled' }],
      ['past', { is_past: true }],
    ])('keeps the link for a %s event', (_name, changes) => {
      renderPage(withBody(changes));

      expect(within(block()).getByRole('link', { name: /Join the meeting/ })).toBeInTheDocument();
    });

    it('never makes a link of an address that is not http or https', () => {
      renderPage(withBody({ meeting_url: NOT_AN_ADDRESS, place: 'Room 2' }));

      expect(within(block()).queryByRole('link')).toBeNull();
      expect(within(block()).getByText('Room 2')).toBeInTheDocument();
    });

    it('says "Meeting address to come" when the address and the place are empty', () => {
      renderPage(withBody({ meeting_url: null, place: null }));

      expect(within(block()).getByText('Meeting address to come')).toBeInTheDocument();
      expect(within(block()).queryByRole('link')).toBeNull();
    });

    it('tells a visitor that the address is for members, with a link to sign in', () => {
      renderPage(withBody({ can_view_location: false, meeting_url: null, place: null }), { reader: 'visitor' });

      expect(within(block()).getByText('The meeting address is for members.')).toBeInTheDocument();
      expect(within(block()).getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
      expect(within(block()).queryByText('Meeting address to come')).toBeNull();
    });

    it('tells an outsider the sentence alone', () => {
      renderPage(withBody({ can_view_location: false, meeting_url: null, place: null }), { reader: 'outsider' });

      expect(within(block()).getByText('The meeting address is for members.')).toBeInTheDocument();
      expect(within(block()).queryByRole('link')).toBeNull();
    });

    // For the members who answer Yes: the server cut the address and the place (#1515)
    const going = (changes: Record<string, unknown> = {}) =>
      withBody({ can_view_location: false, meeting_url: null, place: null, location_visibility: 'going', ...changes });

    it('tells a member of a "going" event that the address is for the members who answer Yes (rule 26)', () => {
      renderPage(going());

      expect(within(block()).getByText('The meeting address is for the members who answer Yes.')).toBeInTheDocument();
      expect(within(block()).queryByRole('link')).toBeNull();
      expect(screen.queryByText(/for the host and the team/)).toBeNull();
    });

    it.each([
      ['a cancelled event', { state: 'cancelled', can_answer: false }],
      ['an event that ended', { is_past: true, can_answer: false }],
    ])('says "answered Yes" when the event takes no answers any more: %s (rule 26)', (_name, changes) => {
      renderPage(going({ ...changes, my_answer: 'no' }));

      expect(within(block()).getByText('The meeting address is for the members who answered Yes.')).toBeInTheDocument();
    });

    it('shows "Join the meeting" and the place after a Yes, when the page gets the event again, and the sentence after a "No" (rule 27)', async () => {
      const set = vi
        .spyOn(knowiiApiClient.communities.events.answer, 'set')
        .mockImplementation((_c, _s, _e, answer) => Promise.resolve({ ok: true, message: '', data: { answer, updated_at: new Date() } }));
      const { rerender } = renderPage(going({ places_left: 28 }));
      const page = (event: SpaceEvent) => (
        <AppDataProvider>
          <EventPage community={eventsCommunity} space={eventsSpace} spaceGroups={[]} event={event} comments={[]} permissions={NONE} />
          <Toaster />
        </AppDataProvider>
      );

      await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));
      await waitFor(() => expect(shared.reload).toHaveBeenCalledWith(EVENT_ALONE));
      expect(set).toHaveBeenCalledWith('c1', 's1', 'e1', 'yes');
      // The block shows only what it received: nothing changes before the server gives the event again
      expect(within(block()).queryByRole('link')).toBeNull();

      // The answer of the load: the server now gives the address and the place to this member
      rerender(
        page(
          withBody({
            location_visibility: 'going',
            my_answer: 'yes',
            places_left: 27,
            can_view_location: true,
            meeting_url: 'https://meet.example.com/abc',
            place: 'Room 2',
          }),
        ),
      );
      expect(within(block()).getByRole('link', { name: /Join the meeting/ })).toHaveAttribute('href', 'https://meet.example.com/abc');
      expect(within(block()).getByText('Room 2')).toBeInTheDocument();
      expect(screen.getByText('27 places left')).toBeInTheDocument();

      // "No": the server cuts them again
      rerender(page(going({ my_answer: 'no', places_left: 28 })));
      expect(within(block()).queryByRole('link')).toBeNull();
      expect(within(block()).getByText('The meeting address is for the members who answer Yes.')).toBeInTheDocument();
    });

    it('changes nothing for a visitor and for a person who is not a member of a "going" event (rule 28)', () => {
      const forOthers = going({ can_answer: false });
      const { unmount } = renderPage(forOthers, { reader: 'visitor' });
      expect(within(block()).getByText('The meeting address is for members.')).toBeInTheDocument();
      expect(within(block()).getByRole('link', { name: 'Sign in' })).toBeInTheDocument();
      unmount();

      renderPage(forOthers, { reader: 'outsider' });
      expect(within(block()).getByText('The meeting address is for members.')).toBeInTheDocument();
      expect(within(block()).queryByRole('link')).toBeNull();
    });
  });

  describe('the answer of the reader (#1515, rules 1, 11, 13 and 61)', () => {
    const answerCalls = () => [
      vi.spyOn(knowiiApiClient.communities.events.answer, 'get'),
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set'),
      vi.spyOn(knowiiApiClient.communities.events.answer, 'clear'),
      vi.spyOn(knowiiApiClient.communities.events.answers, 'list'),
    ];

    it('puts the answer group after the host and before the meeting block (rule 1)', () => {
      renderPage(withBody({ replay_expected: true, places_left: 28 }));

      const host = screen.getByRole('heading', { level: 2, name: 'Host' });
      const group = screen.getByRole('radiogroup', { name: 'Your answer' });
      const meeting = screen.getByRole('heading', { level: 2, name: 'Meeting' });
      expect(host.compareDocumentPosition(group) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(group.compareDocumentPosition(meeting) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(screen.getByText('28 places left')).toBeInTheDocument();
    });

    it('loads the event alone after a save, and no other prop (rule 11)', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue({
        ok: true,
        message: '',
        data: { answer: 'no', updated_at: new Date() },
      });
      renderPage(withBody());

      await userEvent.click(screen.getByRole('radio', { name: 'No' }));

      await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));
      expect(shared.reload).toHaveBeenCalledTimes(1);
      expect(shared.reload).toHaveBeenCalledWith(EVENT_ALONE);
    });

    it.each([404, 403])(
      'loads the whole page again when a save answers %i: the server decides what the reader sees (rule 13)',
      async (status) => {
        vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue({ ok: false, status, message: 'Gone', errors: {} });
        renderPage(withBody());

        await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));

        await waitFor(() => expect(shared.reload).toHaveBeenCalledTimes(1));
        expect(shared.reload).toHaveBeenCalledWith(WHOLE_PAGE);
      },
    );

    it('gives a visitor "Sign in to answer" and sends no request to the routes of the answers (rules 19 and 61)', () => {
      const calls = answerCalls();

      renderPage(withBody({ can_view_location: false, meeting_url: null, can_answer: false, replay_expected: true }), {
        reader: 'visitor',
      });

      expect(screen.getByRole('heading', { level: 2, name: 'Your answer' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Sign in to answer' })).toHaveAttribute('href', '/login');
      expect(screen.queryByRole('radio')).toBeNull();
      expect(screen.queryByRole('button', { name: 'Answers' })).toBeNull();
      for (const call of calls) {
        expect(call).not.toHaveBeenCalled();
      }
    });

    it('gives a person who is not a member no answer group and sends no request (rules 20 and 61)', () => {
      const calls = answerCalls();

      renderPage(withBody({ can_view_location: false, meeting_url: null, can_answer: false }), { reader: 'outsider' });

      expect(screen.queryByText('Your answer')).toBeNull();
      expect(screen.queryByRole('radio')).toBeNull();
      for (const call of calls) {
        expect(call).not.toHaveBeenCalled();
      }
    });

    it('sends no request when the page of a member shows: the answer comes with the event (rule 21)', () => {
      const calls = answerCalls();

      renderPage(withBody({ my_answer: 'yes' }), { permissions: ALL });

      expect(screen.getByRole('radio', { name: 'Yes' })).toBeChecked();
      for (const call of calls) {
        expect(call).not.toHaveBeenCalled();
      }
    });
  });

  describe('"Answers" (#1515, rules 34 and 35)', () => {
    const answers = (yes: number) => ({
      ok: true as const,
      message: '',
      data: {
        items: [{ cuid: 'u2', name: 'Bea Bell', username: 'bea', avatar_url: null }],
        pagination: { limit: 20, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: yes },
        counts: { yes, replay: 0, no: 0 },
      },
    });

    it.each([
      ['the host, the owner and the admins', ALL, true],
      ['a moderator who is not the host', MODERATOR, false],
      ['a member', NONE, false],
      // The host of a draft changes it, and a draft has no answers to read
      ['the host of a draft', { ...ALL, canViewAnswers: false }, false],
    ])('shows the button "Answers" next to "Edit" only with the right: %s', (_name, permissions, shown) => {
      renderPage(withBody(), { permissions });

      const button = screen.queryByRole('button', { name: 'Answers' });
      expect(button !== null).toBe(shown);
      if (button) {
        const edit = screen.getByRole('link', { name: 'Edit' });
        expect(edit.parentElement).toBe(button.parentElement);
      }
    });

    it('shows no button to a visitor and to a person who is not a member', () => {
      const { unmount } = renderPage(withBody({ can_view_location: false, meeting_url: null, can_answer: false }), { reader: 'visitor' });
      expect(screen.queryByRole('button', { name: 'Answers' })).toBeNull();
      unmount();

      renderPage(withBody({ can_view_location: false, meeting_url: null, can_answer: false }), { reader: 'outsider' });
      expect(screen.queryByRole('button', { name: 'Answers' })).toBeNull();
    });

    it.each(['{Enter}', ' '])('opens the dialog with the key "%s", and Escape gives the focus back to the button', async (key) => {
      const list = vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockResolvedValue(answers(1));
      renderPage(withBody(), { permissions: ALL });
      const button = screen.getByRole('button', { name: 'Answers' });
      expect(list).not.toHaveBeenCalled();

      button.focus();
      await userEvent.keyboard(key);

      const dialog = await screen.findByRole('dialog', { name: 'Answers' });
      expect(dialog).toHaveAccessibleDescription('The host, the owner and the admins see this list. Other members do not.');
      expect(await within(dialog).findByRole('link', { name: 'Bea Bell' })).toHaveAttribute('href', '/community/tests/member/bea');
      expect(list).toHaveBeenCalledWith('c1', 's1', 'e1', 'yes', 20, 0);

      await userEvent.keyboard('{Escape}');
      await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
      await waitFor(() => expect(button).toHaveFocus());
    });

    it('closes the dialog and loads the page again when the list is no longer for the reader (rule 42)', async () => {
      vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockResolvedValue({
        ok: false,
        status: 403,
        message: 'This action is unauthorized.',
        errors: {},
      });
      renderPage(withBody(), { permissions: ALL });

      await userEvent.click(screen.getByRole('button', { name: 'Answers' }));

      await waitFor(() => expect(shared.reload).toHaveBeenCalledTimes(1));
      expect(shared.reload).toHaveBeenCalledWith(WHOLE_PAGE);
      await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    });
  });

  describe('the actions (rule 22)', () => {
    it.each(['member', 'outsider', 'visitor'] as const)('shows no action to a %s without a right', (reader) => {
      renderPage(withBody(reader === 'member' ? {} : { can_view_location: false, meeting_url: null }), { reader });

      expect(screen.queryByRole('link', { name: 'Edit' })).toBeNull();
      expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
      expect(screen.queryByRole('button', { name: 'Event actions' })).toBeNull();
    });

    it('gives the host of a published event "Edit" and the menu: cancel, back to draft, duplicate, delete', async () => {
      renderPage(withBody(), { permissions: ALL });

      expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
        'href',
        '/community/tests/space/office-hours/event/weekly-review/edit',
      );
      expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
      await openMenu();
      expect(menuEntries()).toEqual(['Cancel event', 'Back to draft', 'Duplicate', 'Delete']);
    });

    it('gives the host of a draft "Edit", "Publish", and the menu: duplicate, delete', async () => {
      renderPage(withBody({ state: 'draft' }), { permissions: ALL });

      expect(screen.getByRole('link', { name: 'Edit' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();
      await openMenu();
      expect(menuEntries()).toEqual(['Duplicate', 'Delete']);
    });

    it('gives the host of a cancelled event the menu: publish again, duplicate, delete', async () => {
      renderPage(withBody({ state: 'cancelled' }), { permissions: ALL });

      await openMenu();
      expect(menuEntries()).toEqual(['Publish again', 'Duplicate', 'Delete']);
    });

    it('does not offer "Back to draft" once the event started', async () => {
      renderPage(
        withBody({
          starts_at: new Date(EVENT_TEST_NOW.getTime() - 600_000).toISOString(),
          ends_at: new Date(EVENT_TEST_NOW.getTime() + 600_000).toISOString(),
        }),
        {
          permissions: ALL,
        },
      );

      await openMenu();
      expect(menuEntries()).toEqual(['Cancel event', 'Duplicate', 'Delete']);
    });

    it('gives a moderator the menu with "Cancel event" and "Delete", and no "Edit": they cannot open the form', async () => {
      renderPage(withBody(), { permissions: MODERATOR });

      expect(screen.queryByRole('link', { name: 'Edit' })).toBeNull();
      await openMenu();
      expect(menuEntries()).toEqual(['Cancel event', 'Delete']);
    });

    it('gives a host who may not create in the space no "Duplicate"', async () => {
      renderPage(withBody(), { permissions: { ...ALL, canDuplicate: false } });

      await openMenu();
      expect(menuEntries()).toEqual(['Cancel event', 'Back to draft', 'Delete']);
    });

    it('publishes a draft, says so and loads the page again', async () => {
      const setState = vi
        .spyOn(knowiiApiClient.communities.events, 'setState')
        .mockResolvedValue({ ok: true, message: '', data: withBody() });
      renderPage(withBody({ state: 'draft' }), { permissions: ALL });

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));

      expect(setState).toHaveBeenCalledWith('c1', 's1', 'e1', 'published');
      await waitFor(() => expect(shared.reload).toHaveBeenCalled());
      expect(await screen.findByText('Event published')).toBeInTheDocument();
    });

    it('gives the focus to the button of the menu when "Publish" goes away with the draft', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'setState').mockResolvedValue({ ok: true, message: '', data: withBody() });
      shared.user = { cuid: 'me', time_zone: 'America/New_York' };
      const tree = (event: SpaceEvent) => (
        <AppDataProvider>
          <EventPage community={eventsCommunity} space={eventsSpace} spaceGroups={[]} event={event} comments={[]} permissions={ALL} />
        </AppDataProvider>
      );
      const { rerender } = render(tree(withBody({ state: 'draft' })));

      await userEvent.click(screen.getByRole('button', { name: 'Publish' }));
      await waitFor(() => expect(shared.reload).toHaveBeenCalled());
      // The page loads again: the event is published, and the button "Publish" is gone
      rerender(tree(withBody()));

      expect(screen.queryByRole('button', { name: 'Publish' })).toBeNull();
      await waitFor(() => expect(screen.getByRole('button', { name: 'Event actions' })).toHaveFocus());
    });

    it('asks before a cancel, with the words of the question, and cancels only after the yes', async () => {
      const setState = vi
        .spyOn(knowiiApiClient.communities.events, 'setState')
        .mockResolvedValue({ ok: true, message: '', data: withBody({ state: 'cancelled' }) });
      renderPage(withBody(), { permissions: ALL });

      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Cancel event' }));
      const dialog = await screen.findByRole('dialog', { name: 'Cancel this event?' });
      expect(within(dialog).getByText('It stays visible with the label Cancelled.')).toBeInTheDocument();
      expect(setState).not.toHaveBeenCalled();

      await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel event' }));

      expect(setState).toHaveBeenCalledWith('c1', 's1', 'e1', 'cancelled');
      await waitFor(() => expect(shared.reload).toHaveBeenCalled());
      expect(await screen.findByText('Event cancelled')).toBeInTheDocument();
    });

    it('changes nothing when the reader keeps the event', async () => {
      const setState = vi.spyOn(knowiiApiClient.communities.events, 'setState');
      renderPage(withBody(), { permissions: ALL });

      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Cancel event' }));
      const dialog = await screen.findByRole('dialog', { name: 'Cancel this event?' });
      await userEvent.click(within(dialog).getByRole('button', { name: 'Keep the event' }));

      await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
      expect(setState).not.toHaveBeenCalled();
      expect(shared.reload).not.toHaveBeenCalled();
    });

    it('asks before a delete, then goes back to the space', async () => {
      const remove = vi.spyOn(knowiiApiClient.communities.events, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
      renderPage(withBody(), { permissions: ALL });

      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
      const dialog = await screen.findByRole('dialog', { name: 'Delete this event?' });
      // #1516: the delete takes the comments and the answers with it
      expect(
        within(dialog).getByText('Its comments and the answers of members are deleted too. This cannot be undone.'),
      ).toBeInTheDocument();
      expect(remove).not.toHaveBeenCalled();

      await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

      expect(remove).toHaveBeenCalledWith('c1', 's1', 'e1');
      await waitFor(() => expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours'));
      expect(await screen.findByText('Event deleted')).toBeInTheDocument();
    });

    it.each([
      ['the host', ALL],
      ['a moderator with the right to delete content', MODERATOR],
    ])(
      'offers %s to cancel first an event that members still expect, and its "Cancel event" cancels it (#1516)',
      async (_name, permissions) => {
        const setState = vi
          .spyOn(knowiiApiClient.communities.events, 'setState')
          .mockResolvedValue({ ok: true, message: '', data: withBody({ state: 'cancelled' }) });
        const remove = vi.spyOn(knowiiApiClient.communities.events, 'delete');
        const { container } = renderPage(withBody(), { permissions: { ...permissions, mustCancelBeforeDelete: true } });

        await openMenu();
        await userEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
        const dialog = await screen.findByRole('dialog', { name: 'Cancel this event first?' });
        expect(within(dialog).getByText('Members answered Yes or Replay only. Cancel it first, so members are told.')).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Keep the event' })).toHaveFocus();
        expect(within(dialog).queryByRole('button', { name: 'Delete' })).toBeNull();
        const results = await axe.run(container.ownerDocument.body, {
          rules: { 'color-contrast': { enabled: false }, region: { enabled: false } },
        });
        expect(results.violations).toEqual([]);

        await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel event' }));

        expect(setState).toHaveBeenCalledWith('c1', 's1', 'e1', 'cancelled');
        expect(remove).not.toHaveBeenCalled();
        await waitFor(() => expect(shared.reload).toHaveBeenCalled());
        expect(await screen.findByText('Event cancelled')).toBeInTheDocument();
      },
    );

    it('shows the words of the server when a delete is refused after the page loaded (#1516)', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'delete').mockResolvedValue({
        ok: false,
        status: 422,
        message: 'Cancel it first, so members are told.',
        errors: {},
      });
      renderPage(withBody(), { permissions: ALL });

      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
      const dialog = await screen.findByRole('dialog', { name: 'Delete this event?' });
      await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

      await waitFor(() =>
        expect(document.querySelector('[data-slot="event-actions-error"]')).toHaveTextContent('Cancel it first, so members are told.'),
      );
      expect(shared.visit).not.toHaveBeenCalled();
    });

    it('publishes a cancelled event again and puts a published event back to draft, without a question', async () => {
      const setState = vi
        .spyOn(knowiiApiClient.communities.events, 'setState')
        .mockResolvedValue({ ok: true, message: '', data: withBody() });
      const { unmount } = renderPage(withBody({ state: 'cancelled' }), { permissions: ALL });
      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Publish again' }));
      expect(setState).toHaveBeenLastCalledWith('c1', 's1', 'e1', 'published');
      await waitFor(() => expect(shared.reload).toHaveBeenCalledTimes(1));
      unmount();

      renderPage(withBody(), { permissions: ALL });
      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Back to draft' }));
      expect(setState).toHaveBeenLastCalledWith('c1', 's1', 'e1', 'draft');
      await waitFor(() => expect(shared.reload).toHaveBeenCalledTimes(2));
      expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('duplicates the event and opens the form of the copy', async () => {
      const duplicate = vi.spyOn(knowiiApiClient.communities.events, 'duplicate').mockResolvedValue({
        ok: true,
        message: '',
        data: eventFixture('e2', 'Weekly review', { slug: 'weekly-review-2', state: 'draft' }),
      });
      renderPage(withBody(), { permissions: ALL });

      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Duplicate' }));

      expect(duplicate).toHaveBeenCalledWith('c1', 's1', 'e1');
      await waitFor(() => expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours/event/weekly-review-2/edit'));
    });

    it('keeps the alert of a refusal in the page before the first refusal, empty and never with display none', () => {
      renderPage(withBody(), { permissions: ALL });

      // An empty region that is `display: none` is not in the accessibility tree: a screen reader can lose its first text
      const alert = document.querySelector('[data-slot="event-actions-error"]');
      expect(alert).toHaveAttribute('role', 'alert');
      expect(alert).toBeEmptyDOMElement();
      expect(alert).toHaveClass('empty:sr-only');
      expect(alert).not.toHaveClass('empty:hidden');
      expect(alert).not.toHaveClass('hidden');
    });

    it('shows the words of the server in an alert when a change is refused (422)', async () => {
      vi.spyOn(knowiiApiClient.communities.events, 'setState').mockResolvedValue({
        ok: false,
        status: 422,
        message: 'An event that started cannot go back to draft. Cancel it instead.',
        errors: {},
      });
      renderPage(withBody(), { permissions: ALL });

      await openMenu();
      await userEvent.click(screen.getByRole('menuitem', { name: 'Back to draft' }));

      const alert = await screen.findByText('An event that started cannot go back to draft. Cancel it instead.');
      expect(alert).toHaveAttribute('role', 'alert');
      expect(shared.reload).not.toHaveBeenCalled();
    });

    it('keeps the focus on "Publish" while it works, and sends one request for two clicks', async () => {
      let answer: (value: { ok: true; message: string; data: SpaceEvent }) => void = () => undefined;
      const setState = vi.spyOn(knowiiApiClient.communities.events, 'setState').mockImplementation(
        () =>
          new Promise((resolve) => {
            answer = resolve;
          }),
      );
      renderPage(withBody({ state: 'draft' }), { permissions: ALL });
      const publish = screen.getByRole('button', { name: 'Publish' });

      await userEvent.click(publish);
      await userEvent.click(publish);

      expect(setState).toHaveBeenCalledTimes(1);
      expect(publish).toHaveFocus();
      expect(publish).toHaveAttribute('aria-busy', 'true');
      expect(publish).not.toBeDisabled();
      await act(async () => answer({ ok: true, message: '', data: withBody() }));
    });
  });

  describe('live updates (rule 23)', () => {
    const channel = 'community.c1.space.s1';
    const emit = (name: string, eventCuid: string, authorCuid = 'someone') =>
      act(() => emitSocketEvent(channel, name, { space_cuid: 's1', event_cuid: eventCuid, author_cuid: authorCuid }));

    it('loads the page again when this event changes or goes away: the server decides what the reader sees', () => {
      renderPage(withBody());

      emit('space.event.updated', 'e1');
      expect(shared.reload).toHaveBeenCalledTimes(1);
      emit('space.event.deleted', 'e1');
      expect(shared.reload).toHaveBeenCalledTimes(2);
    });

    it('loads the page of a draft again when its host publishes it: an admin no longer reads "Draft"', () => {
      // The publication of a draft sends `space.event.created` on the space channel
      renderPage(withBody({ state: 'draft' }));
      expect(screen.getByText('Draft. Only the host, the owner and the admins see it.')).toBeInTheDocument();

      emit('space.event.created', 'e1');
      expect(shared.reload).toHaveBeenCalledTimes(1);
      // Not for another event that was published, nor for the own publication of the reader
      emit('space.event.created', 'e2');
      emit('space.event.created', 'e1', 'me');
      expect(shared.reload).toHaveBeenCalledTimes(1);
    });

    it('does nothing for another event, and for the own change of the reader', () => {
      renderPage(withBody());

      emit('space.event.updated', 'e2');
      emit('space.event.deleted', 'e1', 'me');

      expect(shared.reload).not.toHaveBeenCalled();
    });

    it('never listens for a visitor', () => {
      renderPage(withBody({ can_view_location: false, meeting_url: null }), { reader: 'visitor' });

      emit('space.event.updated', 'e1');
      emit('space.event.answers.changed', 'e1');

      expect(shared.reload).not.toHaveBeenCalled();
    });

    describe('the places changed (#1515, rules 55 and 56)', () => {
      // The signal names the event only: no member
      const places = (eventCuid: string) =>
        act(() => emitSocketEvent(channel, 'space.event.answers.changed', { space_cuid: 's1', event_cuid: eventCuid, author_cuid: null }));

      it('loads the event alone again for this event, and nothing for another event (rule 55)', () => {
        renderPage(withBody({ places_left: 28 }));

        places('e2');
        expect(shared.reload).not.toHaveBeenCalled();

        places('e1');
        expect(shared.reload).toHaveBeenCalledTimes(1);
        expect(shared.reload).toHaveBeenCalledWith(EVENT_ALONE);
      });

      it('keeps the dialog "Answers" open, the focus, the status and the alert of the answer group (rule 55)', async () => {
        vi.spyOn(knowiiApiClient.communities.events.answers, 'list').mockResolvedValue({
          ok: true,
          message: '',
          data: {
            items: [{ cuid: 'u2', name: 'Bea Bell', username: 'bea', avatar_url: null }],
            pagination: { limit: 20, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: 1 },
            counts: { yes: 1, replay: 0, no: 0 },
          },
        });
        vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue({
          ok: false,
          status: 422,
          message: 'This event is full.',
          errors: {},
        });
        const { rerender } = renderPage(withBody({ places_left: 1 }), { permissions: ALL });
        const page = (event: SpaceEvent) => (
          <AppDataProvider>
            <EventPage community={eventsCommunity} space={eventsSpace} spaceGroups={[]} event={event} comments={[]} permissions={ALL} />
            <Toaster />
          </AppDataProvider>
        );

        // A refused Yes: the alert of the group is there, and the focus is in the group
        await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));
        const alert = () => screen.getAllByRole('alert').find((element) => element.textContent === 'This event is full.');
        await waitFor(() => expect(alert()).toBeDefined());
        const focused = document.activeElement;
        shared.reload.mockClear();

        places('e1');
        // The server gives the event again: no place is left
        rerender(page(withBody({ places_left: 0 })));

        expect(shared.reload).toHaveBeenCalledTimes(1);
        expect(shared.reload).toHaveBeenCalledWith(EVENT_ALONE);
        expect(alert()).toBeDefined();
        expect(document.activeElement).toBe(focused);
        expect(screen.getByText('Full')).toBeInTheDocument();

        // The dialog stays open over the same signal
        await userEvent.click(screen.getByRole('button', { name: 'Answers' }));
        const dialog = await screen.findByRole('dialog', { name: 'Answers' });
        expect(await within(dialog).findByRole('link', { name: 'Bea Bell' })).toBeInTheDocument();
        const inDialog = document.activeElement;
        places('e1');
        rerender(page(withBody({ places_left: 0, updated_at: '2026-11-11T08:00:00.000000Z' })));
        expect(screen.getByRole('dialog', { name: 'Answers' })).toBeInTheDocument();
        expect(within(dialog).getByRole('link', { name: 'Bea Bell' })).toBeInTheDocument();
        expect(document.activeElement).toBe(inDialog);
      });

      it('keeps "Saved: Yes" in the status of the group when the signal comes after the save (rule 55)', async () => {
        vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue({
          ok: true,
          message: '',
          data: { answer: 'yes', updated_at: new Date() },
        });
        renderPage(withBody({ places_left: 28 }));

        await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes'));
        places('e1');

        expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');
        expect(screen.getByRole('radio', { name: 'Yes' })).toHaveFocus();
      });

      it('saves nothing when the signal comes while the question "Give up your place?" is open: the event loads, the question stays', async () => {
        const set = vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockResolvedValue({
          ok: true,
          message: '',
          data: { answer: 'no', updated_at: new Date() },
        });
        const { rerender } = renderPage(withBody({ places_left: 0, my_answer: 'yes' }));

        // A member with a Yes on a full event leaves it: the page asks first
        await userEvent.click(screen.getByRole('radio', { name: 'No' }));
        const dialog = await screen.findByRole('dialog', { name: 'Give up your place?' });
        expect(set).not.toHaveBeenCalled();

        // Another member left: a place is free. The page gets the event, and sends no answer by itself
        places('e1');
        rerender(
          <AppDataProvider>
            <EventPage
              community={eventsCommunity}
              space={eventsSpace}
              spaceGroups={[]}
              event={withBody({ places_left: 1, my_answer: 'yes' })}
              comments={[]}
              permissions={NONE}
            />
            <Toaster />
          </AppDataProvider>,
        );
        expect(shared.reload).toHaveBeenCalledTimes(1);
        expect(shared.reload).toHaveBeenCalledWith(EVENT_ALONE);
        expect(set).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog', { name: 'Give up your place?' })).toBe(dialog);

        // The member confirms: one save, then one load of the event
        await userEvent.click(within(dialog).getByRole('button', { name: 'Give up my place' }));
        expect(set).toHaveBeenCalledTimes(1);
        expect(set).toHaveBeenCalledWith('c1', 's1', 'e1', 'no');
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved: No'));
        expect(shared.reload).toHaveBeenCalledTimes(2);
        expect(shared.reload).toHaveBeenLastCalledWith(EVENT_ALONE);
      });

      it('does one load, after the save, when the signal comes while the answer is saved (rule 56)', async () => {
        let answer: (result: { ok: true; message: string; data: { answer: 'yes'; updated_at: Date } }) => void = () => undefined;
        vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockImplementation(
          () =>
            new Promise((resolve) => {
              answer = resolve;
            }),
        );
        renderPage(withBody({ places_left: 28 }));

        await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));
        // The server sends the signal of this very answer before it answers the request
        places('e1');
        expect(shared.reload).not.toHaveBeenCalled();

        await act(async () => answer({ ok: true, message: '', data: { answer: 'yes', updated_at: new Date() } }));

        expect(shared.reload).toHaveBeenCalledTimes(1);
        expect(shared.reload).toHaveBeenCalledWith(EVENT_ALONE);

        // A signal after the save is a change of another member: the event loads again
        places('e1');
        expect(shared.reload).toHaveBeenCalledTimes(2);
      });
    });
  });

  describe('one load at a time (#1515): an older event never lands after a newer one', () => {
    const channel = 'community.c1.space.s1';
    const signal = (name: string) =>
      act(() =>
        emitSocketEvent(channel, name, { space_cuid: 's1', event_cuid: 'e1', author_cuid: name.endsWith('changed') ? null : 'someone' }),
      );
    const places = () => signal('space.event.answers.changed');

    interface LoadOptions {
      only?: string[];
      onCancelToken?: (token: { cancel: () => void }) => void;
      onSuccess?: () => void;
      onFinish?: () => void;
    }
    interface Load {
      options: LoadOptions;
      cancelled: boolean;
      ended: boolean;
    }
    // The loads that the page started, in their order
    const loads: Load[] = [];
    const load = (index: number) => loads[index] as Load;

    beforeEach(() => {
      loads.length = 0;
      // The router as Inertia 3.11 runs a load (`Request.send` and `Request.cancel` of @inertiajs/core): it gives the
      // cancel function before `reload` returns, and a cancelled load is aborted and ends (onFinish) without a success
      shared.reload.mockImplementation((options: LoadOptions = {}) => {
        const started: Load = { options, cancelled: false, ended: false };
        loads.push(started);
        options.onCancelToken?.({
          cancel: () => {
            if (started.ended) {
              return;
            }
            started.cancelled = true;
            started.ended = true;
            options.onFinish?.();
          },
        });
      });
    });
    afterEach(() => {
      shared.reload.mockReset();
    });

    const page = (event: SpaceEvent) => (
      <AppDataProvider>
        <EventPage community={eventsCommunity} space={eventsSpace} spaceGroups={[]} event={event} comments={[]} permissions={NONE} />
        <Toaster />
      </AppDataProvider>
    );
    /**
     * The answer of the server to a load arrives: the page gets its event, but never from a load that was cancelled
     */
    const land = (started: Load, rerender: (tree: React.ReactElement) => void, event: SpaceEvent) => {
      if (started.cancelled || started.ended) {
        return false;
      }
      act(() => rerender(page(event)));
      started.ended = true;
      started.options.onSuccess?.();
      started.options.onFinish?.();
      return true;
    };
    const slowSave = () => {
      let answer: (result: { ok: true; message: string; data: { answer: 'yes'; updated_at: Date } }) => void = () => undefined;
      vi.spyOn(knowiiApiClient.communities.events.answer, 'set').mockImplementation(
        () =>
          new Promise((resolve) => {
            answer = resolve;
          }),
      );
      return async () => act(async () => answer({ ok: true, message: '', data: { answer: 'yes', updated_at: new Date() } }));
    };

    it('cancels load A when a save starts, starts load B after the save, and only B lands', async () => {
      const endSave = slowSave();
      const { rerender } = renderPage(withBody({ places_left: 28 }));

      // Load A: the places changed. The server reads the event before the answer of the reader exists
      places();
      expect(loads).toHaveLength(1);
      expect(load(0).options.only).toEqual(['event']);
      expect(load(0).cancelled).toBe(false);

      // The save starts: A is cancelled, and no load starts while the save runs
      await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));
      expect(load(0).cancelled).toBe(true);
      places();
      expect(loads).toHaveLength(1);

      // The save ends: load B starts
      await endSave();
      expect(loads).toHaveLength(2);
      expect(load(1).options.only).toEqual(['event']);
      expect(load(1).cancelled).toBe(false);

      // A holds the event of before the answer. It arrives last and never lands; B lands
      expect(land(load(1), rerender, withBody({ my_answer: 'yes', places_left: 27 }))).toBe(true);
      expect(land(load(0), rerender, withBody({ my_answer: null, places_left: 28 }))).toBe(false);
      expect(screen.getByRole('radio', { name: 'Yes' })).toBeChecked();
      expect(screen.getByText('27 places left')).toBeInTheDocument();
      expect(screen.getByRole('status')).toHaveTextContent('Saved: Yes');
    });

    it('cancels the load that runs when a new load starts, and leaves a load that ended alone', () => {
      const { rerender } = renderPage(withBody({ places_left: 28 }));

      places();
      places();
      expect(loads).toHaveLength(2);
      expect(load(0).cancelled).toBe(true);
      expect(load(1).cancelled).toBe(false);

      // The second load lands: the next one has nothing to cancel
      expect(land(load(1), rerender, withBody({ places_left: 27 }))).toBe(true);
      places();
      expect(loads).toHaveLength(3);
      expect(load(1).cancelled).toBe(false);
      expect(load(2).cancelled).toBe(false);
    });

    it('loads the whole page in place of a load of the whole page that it cancelled, until one lands', () => {
      const { rerender } = renderPage(withBody({ places_left: 28 }));

      // The event changed: every prop. Then the places changed: the event alone would lose the other props
      signal('space.event.updated');
      expect(load(0).options.only).toBeUndefined();
      places();
      expect(load(0).cancelled).toBe(true);
      expect(load(1).options.only).toBeUndefined();

      // The whole page landed: the next load of the places is of the event alone
      expect(land(load(1), rerender, withBody({ places_left: 27 }))).toBe(true);
      places();
      expect(load(2).options.only).toEqual(['event']);
    });

    it('does one load of the whole page after the save, when the event changed while the answer was saved', async () => {
      const endSave = slowSave();
      renderPage(withBody({ places_left: 28 }));

      await userEvent.click(screen.getByRole('radio', { name: 'Yes' }));
      signal('space.event.updated');
      places();
      expect(loads).toHaveLength(0);

      await endSave();
      expect(loads).toHaveLength(1);
      expect(load(0).options.only).toBeUndefined();
    });
  });

  describe('accessibility (design 11.6)', () => {
    const cover = {
      cuid: 'up1',
      url: '/uploads/cover.webp',
      mime_type: 'image/webp',
      width: 1600,
      height: 900,
      size_bytes: 1000,
      alt: 'x',
    };

    it.each([
      ['the host of a draft', withBody({ state: 'draft', cover, cover_alt: 'The team', can_answer: false }), { permissions: ALL }],
      ['a member of a cancelled event', withBody({ state: 'cancelled', place: 'Room 2', can_answer: false, my_answer: 'yes' }), {}],
      ['a member who did not answer an event with a limit and a replay', withBody({ replay_expected: true, places_left: 28 }), {}],
      [
        'a member who answered Yes on a "going" event',
        withBody({ replay_expected: true, places_left: 27, my_answer: 'yes', location_visibility: 'going' }),
        {},
      ],
      ['a member of a full event', withBody({ replay_expected: true, places_left: 0 }), {}],
      ['the host of a published event, with "Answers"', withBody({ capacity: 40, places_left: 28 }), { permissions: ALL }],
      ['a visitor', withBody({ can_view_location: false, meeting_url: null, place: null, host: null }), { reader: 'visitor' as const }],
      [
        'an outsider of a past event',
        withBody({ can_view_location: false, meeting_url: null, is_past: true }),
        { reader: 'outsider' as const },
      ],
    ])('has no automated violation for %s (axe)', async (_name, event, options) => {
      const { container } = renderPage(event, options);

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });

    it('has no automated violation with the menu open (axe)', async () => {
      renderPage(withBody(), { permissions: ALL });
      await openMenu();

      const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

      expect(results.violations.map((violation) => violation.id)).toEqual([]);
    });
  });
});
