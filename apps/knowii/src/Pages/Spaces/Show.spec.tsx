import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import type { Community, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
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
