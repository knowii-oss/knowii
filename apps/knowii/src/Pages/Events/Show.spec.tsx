import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { Community, EventPermissions, SpaceEvent } from '@knowii/common';
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

const NONE: EventPermissions = { canUpdate: false, canCancel: false, canDelete: false, canDuplicate: false };
const ALL: EventPermissions = { canUpdate: true, canCancel: true, canDelete: true, canDuplicate: true };
// A moderator with the right to delete content: cancels and deletes the events of others, and does not edit them
const MODERATOR: EventPermissions = { canUpdate: false, canCancel: true, canDelete: true, canDuplicate: false };

type Reader = 'member' | 'outsider' | 'visitor';

interface Options {
  reader?: Reader;
  zone?: string | null;
  permissions?: EventPermissions;
}

function renderPage(event: SpaceEvent, { reader = 'member', zone = 'America/New_York', permissions = NONE }: Options = {}) {
  shared.user = reader === 'visitor' ? null : { cuid: 'me', time_zone: zone };
  const community = { ...eventsCommunity, is_member: reader === 'member' } as Community;
  return render(
    <AppDataProvider>
      <EventPage community={community} space={eventsSpace} spaceGroups={[]} event={event} permissions={permissions} />
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

    it('tells a member of a "going" event that the address is for the host and the team', () => {
      renderPage(withBody({ can_view_location: false, meeting_url: null, place: null, location_visibility: 'going' }));

      expect(within(block()).getByText('The meeting address is for the host and the team.')).toBeInTheDocument();
      expect(within(block()).queryByRole('link')).toBeNull();
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
          <EventPage community={eventsCommunity} space={eventsSpace} spaceGroups={[]} event={event} permissions={ALL} />
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
      expect(within(dialog).getByText('This cannot be undone.')).toBeInTheDocument();
      expect(remove).not.toHaveBeenCalled();

      await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

      expect(remove).toHaveBeenCalledWith('c1', 's1', 'e1');
      await waitFor(() => expect(shared.visit).toHaveBeenCalledWith('/community/tests/space/office-hours'));
      expect(await screen.findByText('Event deleted')).toBeInTheDocument();
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

      expect(shared.reload).not.toHaveBeenCalled();
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
      ['the host of a draft', withBody({ state: 'draft', cover, cover_alt: 'The team' }), { permissions: ALL }],
      ['a member of a cancelled event', withBody({ state: 'cancelled', place: 'Room 2' }), {}],
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
