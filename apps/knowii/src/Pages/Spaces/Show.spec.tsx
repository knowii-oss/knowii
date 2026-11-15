import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import type { Community, Space } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
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
