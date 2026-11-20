import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { ApiResult, Community, CommunityMember, CommunityRole, MemberDirectorySort, Paginated, Pagination } from '@knowii/common';
import { AppDataProvider, COMMUNITY_MEMBERS_PAGE_SIZE, knowiiApiClient, MEMBER_SEARCH_MAX_LENGTH } from '@knowii/common';
import type { ToolkitLinkProps } from '@/Components/ui';
import { AccountTimeZoneProvider, ToolkitProvider } from '@/Components/ui';
import MembersPage, { MEMBER_DIRECTORY_WORDS } from './Index';

// The router of the page: the visits that it starts, and what it listens to
const routerMock = vi.hoisted(() => ({
  get: vi.fn(),
  reload: vi.fn(),
  listeners: {} as Record<string, (event?: unknown) => unknown>,
  on: vi.fn((name: string, listener: (event?: unknown) => unknown) => {
    routerMock.listeners[name] = listener;
    return () => {
      delete routerMock.listeners[name];
    };
  }),
}));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: routerMock,
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({
    children,
    browserPageTitle,
    breadcrumbItems,
  }: {
    children: React.ReactNode;
    browserPageTitle: string;
    breadcrumbItems: { label: string; href?: string }[];
  }) => (
    <main data-testid="layout" data-title={browserPageTitle} data-path={breadcrumbItems.map((item) => item.label).join(' > ')}>
      {children}
    </main>
  ),
}));

vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children, contentWidth }: { children: React.ReactNode; contentWidth?: string }) => (
    <div data-testid="shell" data-width={contentWidth ?? 'wide'}>
      {children}
    </div>
  ),
}));

// What a press on a sort does in the test: nothing leaves the page
const pressed: string[] = [];

/**
 * The link of the application for the toolkit ("A to Z", "Newest", "Manage members"): a press is noted, and stays in
 * the page
 */
function VisitLink({ href, children, className, 'aria-current': ariaCurrent }: ToolkitLinkProps) {
  return (
    <a
      href={href}
      className={className}
      aria-current={ariaCurrent}
      onClick={(event) => {
        event.preventDefault();
        pressed.push(href);
      }}
    >
      {children}
    </a>
  );
}

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
    writable: true,
  });
  // jsdom has no scrollIntoView: the first new entry scrolls into view after a load
  Element.prototype.scrollIntoView = vi.fn();
});

const community = { cuid: 'c1', name: 'Tests', slug: 'tests', visibility: 'private' } as Community;

const memberOf = (cuid: string, name: string, changes: Partial<CommunityMember> = {}): CommunityMember => ({
  cuid,
  name,
  username: name.toLowerCase().replace(/\s+/g, '-'),
  avatar_url: null,
  headline: '',
  role: 'member',
  member_since: '2026-06-01T10:00:00.000000Z',
  ...changes,
});

const paginationOf = (nextOffset: number | null, totalCount: number, offset = 0): Pagination => ({
  limit: COMMUNITY_MEMBERS_PAGE_SIZE,
  offset,
  previousOffset: offset > 0 ? 0 : null,
  nextOffset,
  currentPage: 1,
  pageCount: Math.max(1, Math.ceil(totalCount / COMMUNITY_MEMBERS_PAGE_SIZE)),
  totalCount,
});

const ada = memberOf('u1', 'Ada Lovelace', { username: 'ada', role: 'owner', headline: 'Writes about engines' });
const grace = memberOf('u2', 'Grace Hopper', { username: 'grace', role: 'admin' });
const linus = memberOf('u3', 'Linus Pauling', { username: 'linus', role: 'moderator' });
const mary = memberOf('u4', 'Mary Shelley', { username: 'mary' });
const everyone = [ada, grace, linus, mary];

interface Options {
  members?: CommunityMember[];
  nextOffset?: number | null;
  totalCount?: number;
  sort?: MemberDirectorySort;
  query?: string;
  canManageMembers?: boolean;
}

function tree({ members = everyone, nextOffset = null, totalCount, sort = 'name', query = '', canManageMembers = false }: Options = {}) {
  const page: Paginated<CommunityMember> = { items: members, pagination: paginationOf(nextOffset, totalCount ?? members.length) };
  return (
    <AppDataProvider>
      <AccountTimeZoneProvider value="Europe/Brussels">
        <ToolkitProvider linkComponent={VisitLink}>
          <MembersPage
            community={community}
            spaceGroups={[]}
            members={page}
            sort={sort}
            query={query}
            canManageMembers={canManageMembers}
          />
        </ToolkitProvider>
      </AccountTimeZoneProvider>
    </AppDataProvider>
  );
}

const renderPage = (options: Options = {}) => render(tree(options));

const list = () => screen.getByRole('list', { name: 'Members' });
const entries = () => within(list()).getAllByRole('listitem');
const names = () => entries().map((entry) => within(entry).getByRole('link').textContent);
const entryOf = (name: string) => entries().find((entry) => within(entry).queryByRole('link', { name }) !== null) as HTMLElement;
const field = () => screen.getByRole('searchbox', { name: 'Search the members' });
// The status line of the page (the spinner of a busy button is a status too)
const status = () => screen.getAllByRole('status').find((element) => element.tagName === 'P') as HTMLElement;
const loadMoreButton = () => screen.queryByRole('button', { name: 'Load more' });

const answerOf = (items: CommunityMember[], nextOffset: number | null, totalCount: number): ApiResult<Paginated<CommunityMember>> => ({
  ok: true,
  message: '',
  data: { items, pagination: paginationOf(nextOffset, totalCount, COMMUNITY_MEMBERS_PAGE_SIZE) },
});

// What the page asks the router for with a search: only the list, in place of the entry of the history, with its state,
// and its own answer to a refusal of the server
const SEARCH_VISIT = {
  replace: true,
  preserveState: true,
  preserveScroll: true,
  only: ['members', 'sort', 'query'],
  onHttpException: expect.any(Function),
};

// The address of the directory in the test, and the events of the router as the page gets them
const DIRECTORY_ADDRESS = 'http://localhost/members.index/tests';
const routerEvent = (name: string, detail?: object): unknown => {
  let result: unknown;
  act(() => {
    result = routerMock.listeners[name]?.({ detail });
  });
  return result;
};
/** A visit starts: a search, a press on a sort, a reload, or a visit of another page */
const startVisit = (address = DIRECTORY_ADDRESS) => routerEvent('start', { visit: { url: new URL(address) } });
/** The visit ends, with or without an answer */
const finishVisit = () => routerEvent('finish');
/** The visit got a new page or new props */
const succeedVisit = () => routerEvent('success');
/** The answer of the server to the visit is not a page: what the listener of the page answers to the router */
const refuseVisit = (status: number) => routerEvent('httpException', { response: { status, data: 'Too Many Requests', headers: {} } });
/** The answer of the search visit itself, through the handler that the page gave to the router */
const refuseSearch = (status: number): unknown => {
  const options = routerMock.get.mock.lastCall?.[2] as { onHttpException: (response: { status: number }) => unknown };
  let result: unknown;
  act(() => {
    result = options.onHttpException({ status });
  });
  return result;
};
const TOO_MANY = 'Too many searches. Wait a minute, then try again.';

describe('the member directory (#148)', () => {
  beforeEach(() => {
    routerMock.get.mockClear();
    pressed.length = 0;
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  describe('the page', () => {
    it('has the title "Members", the name of the community in the browser title, and the path to the community', () => {
      renderPage();

      expect(screen.getByRole('heading', { level: 1, name: 'Members' })).toBeInTheDocument();
      expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
      expect(screen.getByTestId('layout')).toHaveAttribute('data-title', 'Members - Tests');
      expect(screen.getByTestId('layout')).toHaveAttribute('data-path', 'Tests > Members');
      // The default content width: the list takes the width that the navigation leaves
      expect(screen.getByTestId('shell')).toHaveAttribute('data-width', 'wide');
    });

    it('shows "Manage members" only to a reader who can manage them, as a link to the settings', () => {
      const { unmount } = renderPage({ canManageMembers: true });

      expect(screen.getByRole('link', { name: 'Manage members' })).toHaveAttribute('href', '/communities.settings/tests');

      unmount();
      renderPage({ canManageMembers: false });

      expect(screen.queryByRole('link', { name: 'Manage members' })).toBeNull();
    });

    it('keeps its words in one object', () => {
      expect(MEMBER_DIRECTORY_WORDS.count(1)).toBe('1 member');
      expect(MEMBER_DIRECTORY_WORDS.count(128)).toBe('128 members');
      expect(MEMBER_DIRECTORY_WORDS.found(0)).toBe('No member found');
      expect(MEMBER_DIRECTORY_WORDS.found(1)).toBe('1 member found');
      expect(MEMBER_DIRECTORY_WORDS.found(3)).toBe('3 members found');
      expect(MEMBER_DIRECTORY_WORDS.loaded(20)).toBe('20 more loaded');
      expect(MEMBER_DIRECTORY_WORDS.tooManySearches).toBe(TOO_MANY);
    });
  });

  describe('the list and its entries', () => {
    it('is a real list named "Members", with one item for each member, in the order of the server', () => {
      renderPage();

      expect(entries()).toHaveLength(4);
      expect(names()).toEqual(['Ada Lovelace', 'Grace Hopper', 'Linus Pauling', 'Mary Shelley']);
      expect(list()).toHaveAttribute('aria-busy', 'false');
    });

    it('shows the name, the username, the headline, the role and the date of joining of an entry, in this order', () => {
      renderPage();

      const entry = entryOf('Ada Lovelace');
      // "AL": the initials of the picture, which a screen reader does not get
      expect(entry).toHaveTextContent(/^ALAda Lovelace@adaWrites about enginesOwnerJoined .+ ago$/);
      // The date of joining: the relative text for the eyes, the full date and the zone for a screen reader and a pointer
      const time = entry.querySelector('time') as HTMLTimeElement;
      expect(time).toHaveAttribute('datetime', '2026-06-01T10:00:00.000Z');
      expect(time.title).toContain('2026');
    });

    it('has the name as the only link of an entry, to the member page; the picture is a decoration', () => {
      renderPage();

      const entry = entryOf('Grace Hopper');
      const links = within(entry).getAllByRole('link');
      expect(links).toHaveLength(1);
      expect(links[0]).toHaveAttribute('href', '/members.show/tests/grace');
      expect(links[0]).toHaveClass('touch-target');
      // The initials of the picture are hidden from a screen reader: the name is read one time
      expect(within(entry).getByText('GH')).toHaveAttribute('aria-hidden', 'true');
      expect(within(entry).getAllByText('Grace Hopper')).toHaveLength(1);
    });

    it.each<[CommunityRole, string]>([
      ['owner', 'Owner'],
      ['admin', 'Admin'],
      ['moderator', 'Moderator'],
    ])('shows the role of the %s as text', (role, label) => {
      renderPage({ members: [memberOf('u9', 'Rosalind Franklin', { role })] });

      expect(within(entryOf('Rosalind Franklin')).getByText(label)).toBeInTheDocument();
    });

    it('shows no role for a plain member', () => {
      renderPage({ members: [mary] });

      const entry = entryOf('Mary Shelley');
      expect(within(entry).queryByText('Member')).toBeNull();
      expect(entry).toHaveTextContent(/^MSMary Shelley@maryJoined .+ ago$/);
    });

    it('shows the headline as plain text, and nothing for an empty one', () => {
      renderPage({ members: [memberOf('u9', 'Rosalind Franklin', { headline: '<b>Bold</b> [link](https://example.com)' }), mary] });

      const entry = entryOf('Rosalind Franklin');
      expect(within(entry).getByText('<b>Bold</b> [link](https://example.com)')).toHaveClass('wrap-anywhere');
      expect(entry.querySelector('b')).toBeNull();
      expect(within(entry).getAllByRole('link')).toHaveLength(1);
      // No empty line for a member without a headline
      expect(entryOf('Mary Shelley').querySelectorAll('p')).toHaveLength(1);
    });

    it('cuts a long name and a long username, so that an entry never scrolls sideways', () => {
      const long = memberOf('u9', 'A'.repeat(120), { username: 'b'.repeat(60) });
      renderPage({ members: [long] });

      const entry = entries()[0] as HTMLElement;
      expect(within(entry).getByText('A'.repeat(120))).toHaveClass('truncate');
      expect(within(entry).getByText(`@${'b'.repeat(60)}`)).toHaveClass('truncate');
    });

    it('has one column on a narrow container and two columns on a wide one (a container query)', () => {
      renderPage();

      expect(list()).toHaveClass('grid', '@3xl:grid-cols-2');
    });
  });

  describe('the status line', () => {
    it.each([
      [{ totalCount: 128 }, '128 members'],
      [{ members: [ada], totalCount: 1 }, '1 member'],
      [{ members: [ada, grace, linus], query: 'a', totalCount: 3 }, '3 members found'],
      [{ members: [ada], query: 'ada', totalCount: 1 }, '1 member found'],
      [{ members: [], query: 'zzz', totalCount: 0 }, 'No member found'],
    ])('says how many members the list holds: %#', (options, text) => {
      renderPage(options);

      expect(status()).toHaveTextContent(new RegExp(`^${text}$`));
      expect(status()).toHaveAttribute('aria-live', 'polite');
      expect(status()).toBeVisible();
    });
  });

  describe('the search', () => {
    it('is a search landmark with a named search field, and the maximum length of the server', () => {
      renderPage({ query: 'ada' });

      const landmark = screen.getByRole('search', { name: 'Search the members' });
      expect(within(landmark).getByRole('searchbox', { name: 'Search the members' })).toBe(field());
      expect(field()).toHaveAttribute('placeholder', 'Search by name or username');
      expect(field()).toHaveAttribute('maxlength', String(MEMBER_SEARCH_MAX_LENGTH));
      // The address gave the text: the field shows it
      expect(field()).toHaveValue('ada');
    });

    it('visits the page 250 ms after the last key, with the sort kept, for the list only', () => {
      vi.useFakeTimers();
      renderPage({ sort: 'newest' });

      fireEvent.change(field(), { target: { value: 'gr' } });
      act(() => {
        vi.advanceTimersByTime(200);
      });
      // A second key inside the pause starts the pause again
      fireEvent.change(field(), { target: { value: ' Grace ' } });
      act(() => {
        vi.advanceTimersByTime(249);
      });
      expect(routerMock.get).not.toHaveBeenCalled();

      act(() => {
        vi.advanceTimersByTime(1);
      });

      expect(routerMock.get).toHaveBeenCalledTimes(1);
      // The text is trimmed
      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'newest', query: 'Grace' }, SEARCH_VISIT);
    });

    it('keeps the focus and the typed text in the field when the list of the search comes', async () => {
      const { rerender } = renderPage();

      await userEvent.type(field(), 'gra');
      expect(field()).toHaveFocus();
      rerender(tree({ members: [grace], query: 'gra', totalCount: 1 }));

      expect(field()).toHaveFocus();
      expect(field()).toHaveValue('gra');
      expect(names()).toEqual(['Grace Hopper']);
      expect(status()).toHaveTextContent('1 member found');
    });

    it('sends no search text when the field is empty', () => {
      vi.useFakeTimers();
      renderPage({ query: 'ada', members: [ada] });

      fireEvent.change(field(), { target: { value: '  ' } });
      act(() => {
        vi.advanceTimersByTime(250);
      });

      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'name' }, SEARCH_VISIT);
    });

    it('searches at once with Enter, one time', () => {
      vi.useFakeTimers();
      renderPage();

      fireEvent.change(field(), { target: { value: 'ada' } });
      fireEvent.submit(screen.getByRole('search', { name: 'Search the members' }));

      expect(routerMock.get).toHaveBeenCalledTimes(1);
      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'name', query: 'ada' }, SEARCH_VISIT);
      // The pause of the key does not search a second time
      act(() => {
        vi.advanceTimersByTime(1000);
      });
      expect(routerMock.get).toHaveBeenCalledTimes(1);
    });

    it('marks the list as busy while a visit runs', () => {
      renderPage();

      startVisit();
      expect(list()).toHaveAttribute('aria-busy', 'true');

      finishVisit();
      expect(list()).toHaveAttribute('aria-busy', 'false');
    });

    it('searches with the sort that shows when the pause ends, not with the sort of the time of the key', () => {
      vi.useFakeTimers();
      const { rerender } = renderPage({ sort: 'name' });

      // The answer of a press on "Newest" comes while a typed text waits for the end of the pause
      fireEvent.change(field(), { target: { value: 'gr' } });
      rerender(tree({ sort: 'newest' }));
      act(() => {
        vi.advanceTimersByTime(250);
      });

      expect(routerMock.get).toHaveBeenCalledTimes(1);
      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'newest', query: 'gr' }, SEARCH_VISIT);
    });

    it('searches with the pressed sort when the answer of the press is still on its way at the end of the pause', () => {
      vi.useFakeTimers();
      renderPage({ sort: 'name' });

      // A press on "Newest" starts a visit; the member types before its answer comes, and the pause ends first
      startVisit(`${DIRECTORY_ADDRESS}?sort=newest`);
      fireEvent.change(field(), { target: { value: 'gr' } });
      act(() => {
        vi.advanceTimersByTime(250);
      });

      expect(routerMock.get).toHaveBeenCalledTimes(1);
      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'newest', query: 'gr' }, SEARCH_VISIT);
    });

    it('keeps the sort that shows when a visit of another page, or a visit with an unknown sort, starts', () => {
      vi.useFakeTimers();
      renderPage({ sort: 'newest' });

      startVisit('http://localhost/members.show/tests/grace?sort=name');
      startVisit(`${DIRECTORY_ADDRESS}?sort=role`);
      startVisit(DIRECTORY_ADDRESS);
      fireEvent.change(field(), { target: { value: 'gr' } });
      act(() => {
        vi.advanceTimersByTime(250);
      });

      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'newest', query: 'gr' }, SEARCH_VISIT);
    });
  });

  describe('a search or a sort that the server refuses (429)', () => {
    it('keeps the list, says to wait in an alert line, and tells the router that the page handled the answer', async () => {
      renderPage();
      expect(screen.queryByRole('alert')).toBeNull();

      await userEvent.type(field(), 'gra');
      fireEvent.submit(screen.getByRole('search', { name: 'Search the members' }));
      startVisit(`${DIRECTORY_ADDRESS}?sort=name&query=gra`);

      // `false`: the router does not show the answer of the server in a dialog
      expect(refuseSearch(429)).toBe(false);
      finishVisit();

      expect(screen.getByRole('alert').textContent.trim()).toBe(TOO_MANY);
      // The list is the one of before, and the field keeps the focus and the typed text
      expect(names()).toEqual(['Ada Lovelace', 'Grace Hopper', 'Linus Pauling', 'Mary Shelley']);
      expect(status()).toHaveTextContent(/^4 members$/);
      expect(list()).toHaveAttribute('aria-busy', 'false');
      expect(field()).toHaveValue('gra');
      expect(field()).toHaveFocus();
      // The alert is between the status line and the list
      const alert = screen.getByRole('alert');
      expect(status().compareDocumentPosition(alert) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(alert.compareDocumentPosition(list()) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('does the same for a press on a sort, through the events of the router: the sort of before stays', () => {
      renderPage({ sort: 'name' });

      const newest = screen.getByRole('link', { name: 'Newest' });
      newest.focus();
      fireEvent.click(newest);
      startVisit(`${DIRECTORY_ADDRESS}?sort=newest`);

      expect(refuseVisit(429)).toBe(false);
      finishVisit();

      expect(screen.getByRole('alert')).toHaveTextContent(TOO_MANY);
      expect(screen.getByRole('link', { name: 'A to Z' })).toHaveAttribute('aria-current', 'page');
      expect(newest).not.toHaveAttribute('aria-current');
      expect(newest).toHaveFocus();
      expect(names()).toEqual(['Ada Lovelace', 'Grace Hopper', 'Linus Pauling', 'Mary Shelley']);
    });

    it('removes the line at the next visit that succeeds', async () => {
      const { rerender } = renderPage();

      await userEvent.type(field(), 'gra');
      fireEvent.submit(screen.getByRole('search', { name: 'Search the members' }));
      refuseSearch(429);
      expect(screen.getByRole('alert')).toBeInTheDocument();

      // A visit that only starts and ends changes nothing
      startVisit();
      finishVisit();
      expect(screen.getByRole('alert')).toBeInTheDocument();

      fireEvent.submit(screen.getByRole('search', { name: 'Search the members' }));
      startVisit();
      succeedVisit();
      rerender(tree({ members: [grace], query: 'gra', totalCount: 1 }));
      finishVisit();

      expect(screen.queryByRole('alert')).toBeNull();
      expect(names()).toEqual(['Grace Hopper']);
    });

    it('leaves another failure of a visit to the router', () => {
      renderPage();

      fireEvent.submit(screen.getByRole('search', { name: 'Search the members' }));
      startVisit();

      // Not `false`: the router shows the answer as it does on every page
      expect(refuseSearch(500)).toBeUndefined();
      expect(refuseVisit(500)).toBeUndefined();
      expect(refuseVisit(503)).toBeUndefined();
      expect(screen.queryByRole('alert')).toBeNull();
    });

    it('leaves a refusal of a visit of another page to the router', () => {
      renderPage();

      // A press on a name: the visit goes to the page of the member
      startVisit('http://localhost/members.show/tests/grace');

      expect(refuseVisit(429)).toBeUndefined();
      expect(screen.queryByRole('alert')).toBeNull();
    });

    it('stops listening to the router when the page goes', () => {
      const { unmount } = renderPage();
      expect(Object.keys(routerMock.listeners).sort()).toEqual(['finish', 'httpException', 'start', 'success']);

      unmount();

      expect(routerMock.listeners).toEqual({});
    });
  });

  describe('the sort', () => {
    it('is a navigation named "Sort the members" with two links, and marks the open sort', () => {
      const { unmount } = renderPage({ sort: 'name' });

      const sorts = screen.getByRole('navigation', { name: 'Sort the members' });
      expect(within(sorts).getAllByRole('link')).toHaveLength(2);
      expect(within(sorts).getByRole('link', { name: 'A to Z' })).toHaveAttribute('aria-current', 'page');
      expect(within(sorts).getByRole('link', { name: 'Newest' })).not.toHaveAttribute('aria-current');
      expect(within(sorts).getByRole('link', { name: 'A to Z' })).toHaveAttribute('href', '/members.index/tests?sort=name');
      expect(within(sorts).getByRole('link', { name: 'Newest' })).toHaveAttribute('href', '/members.index/tests?sort=newest');

      unmount();
      renderPage({ sort: 'newest' });

      expect(screen.getByRole('link', { name: 'Newest' })).toHaveAttribute('aria-current', 'page');
      expect(screen.getByRole('link', { name: 'A to Z' })).not.toHaveAttribute('aria-current');
    });

    it('keeps the search text in the address of each sort, also the text that was just typed', async () => {
      renderPage({ query: 'ada l', members: [ada] });

      expect(screen.getByRole('link', { name: 'A to Z' })).toHaveAttribute('href', '/members.index/tests?sort=name&query=ada+l');
      expect(screen.getByRole('link', { name: 'Newest' })).toHaveAttribute('href', '/members.index/tests?sort=newest&query=ada+l');

      await userEvent.clear(field());
      await userEvent.type(field(), 'gr&ce ');

      expect(screen.getByRole('link', { name: 'Newest' })).toHaveAttribute('href', '/members.index/tests?sort=newest&query=gr%26ce');
    });

    it('keeps the focus on the pressed sort when its list comes, and the text of the field', async () => {
      const { rerender } = renderPage({ query: 'a', members: [ada, grace] });

      const newest = screen.getByRole('link', { name: 'Newest' });
      newest.focus();
      await userEvent.click(newest);
      expect(pressed).toEqual(['/members.index/tests?sort=newest&query=a']);
      rerender(tree({ sort: 'newest', query: 'a', members: [grace, ada] }));

      // The same element: the link was not built again
      expect(screen.getByRole('link', { name: 'Newest' })).toBe(newest);
      expect(newest).toHaveFocus();
      expect(newest).toHaveAttribute('aria-current', 'page');
      expect(field()).toHaveValue('a');
      expect(names()).toEqual(['Grace Hopper', 'Ada Lovelace']);
    });

    it('lets a press on a sort win over a search that waits for the end of the pause', () => {
      vi.useFakeTimers();
      renderPage();

      fireEvent.change(field(), { target: { value: 'ada' } });
      fireEvent.click(screen.getByRole('link', { name: 'Newest' }));
      act(() => {
        vi.advanceTimersByTime(1000);
      });

      // The link holds the typed text: the search of the pause would bring the old sort back
      expect(pressed).toEqual(['/members.index/tests?sort=newest&query=ada']);
      expect(routerMock.get).not.toHaveBeenCalled();
    });
  });

  describe('a search with no result', () => {
    it('shows the empty state in place of the list, with no "Load more"', () => {
      renderPage({ members: [], query: 'zzz', totalCount: 0 });

      expect(screen.getByText('No member matches this search')).toBeInTheDocument();
      expect(screen.queryByRole('list', { name: 'Members' })).toBeNull();
      expect(loadMoreButton()).toBeNull();
      expect(status()).toHaveTextContent('No member found');
    });

    it('"Clear the search" empties the field, puts the focus in it and asks for the full list at once', async () => {
      const { rerender } = renderPage({ members: [], query: 'zzz', totalCount: 0, sort: 'newest' });

      await userEvent.click(screen.getByRole('button', { name: 'Clear the search' }));

      expect(field()).toHaveValue('');
      expect(field()).toHaveFocus();
      expect(routerMock.get).toHaveBeenCalledTimes(1);
      expect(routerMock.get).toHaveBeenCalledWith('/members.index/tests', { sort: 'newest' }, SEARCH_VISIT);

      rerender(tree({ sort: 'newest' }));

      expect(entries()).toHaveLength(4);
      expect(field()).toHaveFocus();
      expect(screen.queryByText('No member matches this search')).toBeNull();
    });
  });

  describe('"Load more"', () => {
    const next = [mary, memberOf('u5', 'Niels Bohr'), memberOf('u6', 'Otto Hahn')];

    it('shows only while the list has more', () => {
      const { unmount } = renderPage({ nextOffset: 20, totalCount: 25 });
      expect(loadMoreButton()).toBeInTheDocument();

      unmount();
      renderPage({ nextOffset: null });
      expect(loadMoreButton()).toBeNull();
    });

    it('asks the API for the next entries with the sort and the search text of the list, and adds the new ones', async () => {
      const call = vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue(answerOf(next, null, 6));
      renderPage({ nextOffset: 20, totalCount: 6, sort: 'newest', query: 'a' });

      await userEvent.click(loadMoreButton() as HTMLElement);

      expect(call).toHaveBeenCalledTimes(1);
      expect(call).toHaveBeenCalledWith('c1', COMMUNITY_MEMBERS_PAGE_SIZE, 20, 'a', 'newest');
      // "Mary Shelley" was already in the list: 2 new entries, not 3, and no double entry
      await waitFor(() =>
        expect(names()).toEqual(['Ada Lovelace', 'Grace Hopper', 'Linus Pauling', 'Mary Shelley', 'Niels Bohr', 'Otto Hahn']),
      );
      expect(status()).toHaveTextContent(/^6 members found\. 2 more loaded$/);
      // The focus goes to the name of the first new entry
      await waitFor(() => expect(screen.getByRole('link', { name: 'Niels Bohr' })).toHaveFocus());
      // The last page: the button goes
      expect(loadMoreButton()).toBeNull();
    });

    it('asks for the text that the list shows, not for a text that waits in the field', async () => {
      const call = vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue(answerOf([], null, 4));
      renderPage({ nextOffset: 20, totalCount: 25 });

      fireEvent.change(field(), { target: { value: 'typed' } });
      await userEvent.click(loadMoreButton() as HTMLElement);

      expect(call).toHaveBeenCalledWith('c1', COMMUNITY_MEMBERS_PAGE_SIZE, 20, '', 'name');
    });

    it('keeps the button for the next page, and asks for the offset that the server gave', async () => {
      const call = vi
        .spyOn(knowiiApiClient.communities.members, 'list')
        .mockResolvedValueOnce(answerOf([memberOf('u5', 'Niels Bohr')], 40, 45))
        .mockResolvedValueOnce(answerOf([memberOf('u6', 'Otto Hahn')], null, 45));
      renderPage({ nextOffset: 20, totalCount: 45 });

      await userEvent.click(loadMoreButton() as HTMLElement);
      await waitFor(() => expect(status()).toHaveTextContent(/^45 members\. 1 more loaded$/));
      await userEvent.click(loadMoreButton() as HTMLElement);

      await waitFor(() => expect(names()).toContain('Otto Hahn'));
      expect(call).toHaveBeenNthCalledWith(2, 'c1', COMMUNITY_MEMBERS_PAGE_SIZE, 40, '', 'name');
      expect(loadMoreButton()).toBeNull();
    });

    it('shows the message of the API client above the button when the load fails, and the button is the retry', async () => {
      const call = vi
        .spyOn(knowiiApiClient.communities.members, 'list')
        .mockResolvedValueOnce({ ok: false, status: 500, message: 'Failed to load the members.', errors: {} })
        .mockResolvedValueOnce(answerOf([memberOf('u5', 'Niels Bohr')], null, 5));
      renderPage({ nextOffset: 20, totalCount: 5 });

      await userEvent.click(loadMoreButton() as HTMLElement);

      const alert = await screen.findByRole('alert');
      expect(alert).toHaveTextContent('Failed to load the members.');
      // The list did not change, and the alert is before the button in the page
      expect(entries()).toHaveLength(4);
      const button = loadMoreButton() as HTMLElement;
      expect(alert.compareDocumentPosition(button) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(status()).toHaveTextContent(/^5 members$/);

      await userEvent.click(button);

      await waitFor(() => expect(names()).toContain('Niels Bohr'));
      expect(call).toHaveBeenCalledTimes(2);
      expect(screen.queryByRole('alert')).toBeNull();
    });

    it('says to wait in the same error line when the server refuses the load (429), and the button is the retry', async () => {
      const call = vi
        .spyOn(knowiiApiClient.communities.members, 'list')
        .mockResolvedValueOnce({ ok: false, status: 429, message: 'Too Many Requests', errors: {} })
        .mockResolvedValueOnce(answerOf([memberOf('u5', 'Niels Bohr')], null, 5));
      renderPage({ nextOffset: 20, totalCount: 5 });

      await userEvent.click(loadMoreButton() as HTMLElement);

      const alert = await screen.findByRole('alert');
      expect(alert).toHaveTextContent(TOO_MANY);
      expect(alert).not.toHaveTextContent('Too Many Requests');
      expect(screen.getAllByRole('alert')).toHaveLength(1);
      // The same place as the other failures: under the list, above the button
      const button = loadMoreButton() as HTMLElement;
      expect(list().compareDocumentPosition(alert) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(alert.compareDocumentPosition(button) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      expect(entries()).toHaveLength(4);

      await userEvent.click(button);

      await waitFor(() => expect(names()).toContain('Niels Bohr'));
      expect(call).toHaveBeenCalledTimes(2);
      expect(screen.queryByRole('alert')).toBeNull();
    });

    it('takes the count of the members from the answer: a member who joined since the first page is counted', async () => {
      vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue(answerOf([memberOf('u5', 'Niels Bohr')], 40, 46));
      renderPage({ nextOffset: 20, totalCount: 45 });
      expect(status()).toHaveTextContent(/^45 members$/);

      await userEvent.click(loadMoreButton() as HTMLElement);

      await waitFor(() => expect(status()).toHaveTextContent(/^46 members\. 1 more loaded$/));
    });

    it('says only the new count when the next page holds no new entry, and puts the focus on the last name', async () => {
      // 5 members, 4 in the page; the fifth one leaves, then the reader asks for more
      vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue(answerOf([], null, 4));
      renderPage({ nextOffset: 20, totalCount: 5 });
      expect(status()).toHaveTextContent(/^5 members$/);

      await userEvent.click(loadMoreButton() as HTMLElement);

      // The button goes, and the focus is not lost: it is on the last name of the list
      await waitFor(() => expect(loadMoreButton()).toBeNull());
      await waitFor(() => expect(screen.getByRole('link', { name: 'Mary Shelley' })).toHaveFocus());
      expect(status()).toHaveTextContent(/^4 members$/);
      expect(status()).not.toHaveTextContent('more loaded');
      expect(entries()).toHaveLength(4);
      expect(screen.queryByRole('alert')).toBeNull();
    });

    it('says nothing about a load when every entry of the next page shows already, and the button keeps the focus', async () => {
      const call = vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue(answerOf([linus, mary], 40, 45));
      renderPage({ nextOffset: 20, totalCount: 45, query: 'a' });

      await userEvent.click(loadMoreButton() as HTMLElement);

      await waitFor(() => expect(call).toHaveBeenCalledTimes(1));
      await waitFor(() => expect(loadMoreButton()).not.toHaveAttribute('aria-busy', 'true'));
      expect(status()).toHaveTextContent(/^45 members found$/);
      expect(entries()).toHaveLength(4);
      // More pages are left: the button stays, with the focus
      expect(loadMoreButton()).toHaveFocus();
    });

    it('starts the list again when the server gives a new first page: the loaded entries and their status go', async () => {
      vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue(answerOf([memberOf('u5', 'Niels Bohr')], 40, 45));
      const { rerender } = renderPage({ nextOffset: 20, totalCount: 45 });

      await userEvent.click(loadMoreButton() as HTMLElement);
      await waitFor(() => expect(names()).toContain('Niels Bohr'));

      rerender(tree({ sort: 'newest', members: [mary, ada], nextOffset: 20, totalCount: 45 }));

      expect(names()).toEqual(['Mary Shelley', 'Ada Lovelace']);
      expect(status()).toHaveTextContent(/^45 members$/);
      expect(loadMoreButton()).toBeInTheDocument();
    });

    it('drops an answer that comes after another list opened', async () => {
      let answer: (result: ApiResult<Paginated<CommunityMember>>) => void = () => undefined;
      vi.spyOn(knowiiApiClient.communities.members, 'list').mockReturnValue(
        new Promise<ApiResult<Paginated<CommunityMember>>>((resolve) => {
          answer = resolve;
        }),
      );
      const { rerender } = renderPage({ nextOffset: 20, totalCount: 45 });

      await userEvent.click(loadMoreButton() as HTMLElement);
      rerender(tree({ query: 'gra', members: [grace], totalCount: 1 }));
      await act(async () => {
        answer(answerOf([memberOf('u5', 'Niels Bohr')], null, 45));
        await Promise.resolve();
      });

      expect(names()).toEqual(['Grace Hopper']);
      expect(status()).toHaveTextContent(/^1 member found$/);
    });
  });

  describe('accessibility', () => {
    it('has no automated violation with the list, the roles and "Load more" (axe)', async () => {
      const { container } = renderPage({ nextOffset: 20, totalCount: 25, canManageMembers: true });

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });

      expect(results.violations).toEqual([]);
    });

    it('has no automated violation with the empty state (axe)', async () => {
      const { container } = renderPage({ members: [], query: 'zzz', totalCount: 0 });

      const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });

      expect(results.violations).toEqual([]);
    });

    it('gives the tab order of the page: "Manage members", the field, the two sorts, each name, "Load more"', async () => {
      renderPage({ members: [ada, grace], nextOffset: 20, totalCount: 25, canManageMembers: true });

      const order: (string | null)[] = [];
      for (let step = 0; step < 7; step++) {
        await userEvent.tab();
        const element = document.activeElement as HTMLElement;
        order.push(element.getAttribute('aria-label') ?? element.textContent);
      }

      expect(order).toEqual(['Manage members', 'Search the members', 'A to Z', 'Newest', 'Ada Lovelace', 'Grace Hopper', 'Load more']);
    });
  });
});
