import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import type { ToolkitLinkProps } from '@/Components/ui';
import { ToolkitProvider } from '@/Components/ui';
import { eventFixture } from '@/test-utils/events';
import { spyOnFollowApi } from '@/test-utils/notifications';
import SpacePage from './Show';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({
    props: { auth: { user: null }, jetstream: { flash: {} }, newsletter: { enabled: false, turnstileSiteKey: null } },
    url: '/',
  }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));
vi.mock('@/Components/Posts/PostList', () => ({ PostList: () => null }));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

const props = {
  community: { cuid: 'c1', slug: 'tests', name: 'Tests', visibility: 'private' } as Community,
  space: { cuid: 's1', slug: 'news', name: 'News', type: 'discussion', visibility: 'public', emoji: '📰' } as Space,
  spaceGroups: [],
  permissions: { canManageSpaces: false },
  posts: null,
  resources: null,
  pages: null,
  progress: null,
  chat: null,
};

describe('SpacePage, a public space (#1493)', () => {
  it('shows no "Public" label next to the name of the space', () => {
    render(
      <AppDataProvider>
        {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
        <SpacePage {...(props as any)} />
      </AppDataProvider>,
    );

    const heading = screen.getByRole('heading', { level: 1, name: /News/ });
    expect(within(heading).queryByText('Public')).toBeNull();
  });
});

describe('SpacePage, the way in of an open community (#1486)', () => {
  const renderPage = (community: Community) =>
    render(
      <AppDataProvider>
        {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
        <SpacePage {...({ ...props, community } as any)} />
      </AppDataProvider>,
    );

  it('shows "Sign up to join" and "Sign in" in the header to a visitor', () => {
    renderPage({ ...props.community, join_policy: 'open', is_member: false } as Community);

    const header = screen.getByRole('banner');
    expect(within(header).getByRole('link', { name: 'Sign up to join' })).toBeInTheDocument();
    expect(within(header).getByRole('link', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('shows nothing when the community is by invitation', () => {
    renderPage({ ...props.community, join_policy: 'invitation', is_member: false } as Community);

    expect(screen.queryByRole('link', { name: 'Sign up to join' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Join' })).toBeNull();
  });
});

describe('SpacePage, the level of the space (#1497)', () => {
  const renderPage = (community: Community, space: Space = props.space) =>
    render(
      <AppDataProvider>
        {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
        <SpacePage {...({ ...props, community, space } as any)} />
      </AppDataProvider>,
    );

  beforeEach(() => {
    spyOnFollowApi();
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows the level of the space to a member', async () => {
    renderPage({ ...props.community, is_member: true } as Community);

    expect(await screen.findByRole('button', { name: 'Notifications: Only what is for me' })).toBeInTheDocument();
  });

  it('shows no control to a visitor, nor to a signed-in person who is not a member', async () => {
    renderPage({ ...props.community, is_member: false } as Community);
    renderPage({ ...props.community } as Community);

    await waitFor(() => expect(knowiiApiClient.notificationLevels.get).not.toHaveBeenCalled());
    expect(screen.queryByRole('button', { name: /^Notifications:/ })).toBeNull();
  });

  it('shows no control for a link space', async () => {
    renderPage({ ...props.community, is_member: true } as Community, { ...props.space, type: 'link', url: null } as Space);

    expect(knowiiApiClient.notificationLevels.get).not.toHaveBeenCalled();
    expect(screen.queryByRole('button', { name: /^Notifications:/ })).toBeNull();
  });
});

describe('SpacePage, an events space (#1514, design 6.1)', () => {
  const eventsSpace = { ...props.space, cuid: 's9', slug: 'office-hours', name: 'Office hours', type: 'events', emoji: '' } as Space;
  const pagination = { limit: 20, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: 1 };
  // What a press on "Upcoming" or "Past" does: the visit of the router, which gives new props to the same page
  let visit: ((href: string) => void) | null = null;
  afterEach(() => {
    visit = null;
  });
  // The link of the application for the toolkit: a press visits, and stays in the page
  const VisitLink = ({ href, children, className, 'aria-current': ariaCurrent }: ToolkitLinkProps) => (
    <a
      href={href}
      className={className}
      aria-current={ariaCurrent}
      onClick={(event) => {
        event.preventDefault();
        visit?.(href);
      }}
    >
      {children}
    </a>
  );
  const tree = (scope: 'upcoming' | 'past', titles: string[], canContribute: boolean) => (
    <AppDataProvider>
      <ToolkitProvider linkComponent={VisitLink}>
        <SpacePage
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          {...({
            ...props,
            space: eventsSpace,
            permissions: { canManageSpaces: false, canContribute },
            events: {
              items: titles.map((title, index) => eventFixture(`e${index}-${scope}`, title, { is_past: scope === 'past' })),
              pagination,
            },
            eventsScope: scope,
          } as any)}
        />
      </ToolkitProvider>
    </AppDataProvider>
  );

  it('shows the list of the events with "Upcoming" and "Past", and "New event" for a reader who may create one', () => {
    render(tree('upcoming', ['Weekly review'], true));

    expect(screen.getByRole('link', { name: 'Upcoming' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Past' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Weekly review' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'New event' })).toBeInTheDocument();
    // The empty state of the other types of space does not show
    expect(screen.queryByText('No events yet')).toBeNull();
  });

  it('shows no "New event" to a reader who may not create one', () => {
    render(tree('upcoming', ['Weekly review'], false));

    expect(screen.queryByRole('link', { name: 'New event' })).toBeNull();
  });

  it('starts a new list when the scope of the page changes: the link keeps the state of the page', () => {
    const { rerender } = render(tree('upcoming', ['Weekly review'], false));
    expect(screen.getByRole('list', { name: 'Upcoming events' })).toBeInTheDocument();

    // The visit to "?scope=past" gives new props to the same page
    rerender(tree('past', ['Last week'], false));

    expect(screen.getByRole('link', { name: 'Past' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('list', { name: 'Past events' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Last week' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Weekly review' })).toBeNull();
  });

  it('keeps "Upcoming" and "Past" in the page when the keyboard presses one: the focus stays on it, and a status names the new list', async () => {
    const user = userEvent.setup();
    const { rerender } = render(tree('upcoming', ['Weekly review'], false));
    visit = (href) =>
      rerender(href.endsWith('scope=past') ? tree('past', ['Last week'], false) : tree('upcoming', ['Weekly review'], false));
    const past = screen.getByRole('link', { name: 'Past' });

    past.focus();
    await user.keyboard('{Enter}');

    expect(screen.getByRole('list', { name: 'Past events' })).toBeInTheDocument();
    // The same link, not a new one: a link that goes away drops the focus on the page
    expect(screen.getByRole('link', { name: 'Past' })).toBe(past);
    expect(past).toHaveFocus();
    expect(past).toHaveAttribute('aria-current', 'page');
    const status = within(screen.getByRole('region', { name: 'Events in Office hours' })).getByRole('status');
    expect(status).toHaveTextContent('Past events: 1');
    expect(status).toHaveAttribute('aria-live', 'polite');
  });
});
