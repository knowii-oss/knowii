import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Community, Page, Space } from '@knowii/common';
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

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params?: Record<string, string>) => `/page/${params?.['pageSlug'] ?? params?.['spaceSlug'] ?? ''}`,
    writable: true,
  });
});

const now = new Date();
const props = {
  community: { cuid: 'c1', slug: 'tests', name: 'Tests', visibility: 'private' } as Community,
  space: { cuid: 's1', slug: 'start-here', name: 'Start here', type: 'pages', emoji: '📄' } as Space,
  spaceGroups: [],
  page: {
    cuid: 'p2',
    title: 'House rules',
    slug: 'rules',
    position: 1,
    body_html: '<p>Be kind.</p>',
    created_at: now,
    updated_at: now,
  } as Page,
  previous: { title: 'Welcome', slug: 'welcome' },
  next: { title: 'Introduce yourself', slug: 'intro' },
  position: { current: 2, total: 3 },
};

describe('SpacePage', () => {
  it('shows the text and links the previous and the next page (#1179)', () => {
    render(
      <AppDataProvider>
        <SpacePage {...props} permissions={{ canManagePages: false }} />
      </AppDataProvider>,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'House rules' })).toBeInTheDocument();
    expect(screen.getByText('Page 2 of 3')).toBeInTheDocument();
    expect(screen.getByText('Be kind.')).toBeInTheDocument();
    const nav = screen.getByRole('navigation', { name: 'Pages of the space' });
    expect(nav.querySelector('a[rel="prev"]')).toHaveAttribute('href', '/page/welcome');
    expect(nav.querySelector('a[rel="next"]')).toHaveAttribute('href', '/page/intro');
    expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
  });

  it('gives the managers Edit and Delete', () => {
    render(
      <AppDataProvider>
        <SpacePage {...props} previous={null} next={null} permissions={{ canManagePages: true }} />
      </AppDataProvider>,
    );

    expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
  });
});
