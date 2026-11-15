import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import type { Community, NewestPost, SpaceGroup } from '@knowii/common';
import PublicCommunityPage from './Public';

const page = vi.hoisted(() => ({ user: null as { cuid: string } | null }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { visit: vi.fn(), reload: vi.fn() },
}));

vi.mock('@knowii/common', async () => ({
  ...(await vi.importActual<Record<string, unknown>>('@knowii/common')),
  useTypedPage: () => ({ props: { auth: { user: page.user } } }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}${params ? `/${Object.values(params).join('/')}` : ''}`,
    writable: true,
  });
});

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: 'A place to learn',
  visibility: 'public',
  is_member: false,
} as Community;

const newestPosts: NewestPost[] = [
  {
    cuid: 'p1',
    title: 'Welcome everyone',
    slug: 'welcome-everyone',
    excerpt: 'The first words of the post',
    author: { cuid: 'u1', name: 'Ada Lovelace', username: 'ada', avatar_url: null },
    space: { cuid: 's1', slug: 'general', name: 'General', emoji: '💬' },
    created_at: new Date('2026-11-01T10:00:00Z'),
  },
  {
    cuid: 'p2',
    title: 'From a closed account',
    slug: 'from-a-closed-account',
    excerpt: '',
    author: null,
    space: { cuid: 's1', slug: 'general', name: 'General', emoji: '' },
    created_at: new Date('2026-10-31T10:00:00Z'),
  },
];

function renderPage(posts: NewestPost[] = newestPosts) {
  render(<PublicCommunityPage community={community} spaceGroups={[] as SpaceGroup[]} newestPosts={posts} />);
}

describe('The public community page (#1485)', () => {
  it('shows the name, the description and "Sign in" and "Sign up" to a visitor, and a place for "Join"', () => {
    page.user = null;
    renderPage();

    expect(screen.getByRole('heading', { level: 1, name: 'Tests' })).toBeInTheDocument();
    expect(screen.getByText('A place to learn')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
    expect(screen.getByRole('link', { name: 'Sign up' })).toHaveAttribute('href', '/register');
    expect(document.querySelector('[data-slot="join"]')).not.toBeNull();
  });

  it('shows no "Sign in" and no "Sign up" to a signed-in person who is not a member', () => {
    page.user = { cuid: 'u9' };
    renderPage();

    expect(screen.getByRole('heading', { level: 1, name: 'Tests' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Sign in' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Sign up' })).toBeNull();
  });

  it('shows "Sign up to join" and "Sign in" to a visitor when the community is open (#1486)', () => {
    page.user = null;
    render(
      <PublicCommunityPage community={{ ...community, join_policy: 'open' }} spaceGroups={[] as SpaceGroup[]} newestPosts={newestPosts} />,
    );

    const slot = document.querySelector('[data-slot="join"]') as HTMLElement;
    expect(within(slot).getByRole('link', { name: 'Sign up to join' })).toHaveAttribute('href', '/register');
    expect(within(slot).getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('link', { name: 'Sign up' })).toBeNull();
  });

  it('shows "Join" to a signed-in person who is not a member when the community is open (#1486)', () => {
    page.user = { cuid: 'u9' };
    render(
      <PublicCommunityPage community={{ ...community, join_policy: 'open' }} spaceGroups={[] as SpaceGroup[]} newestPosts={newestPosts} />,
    );

    const slot = document.querySelector('[data-slot="join"]') as HTMLElement;
    expect(within(slot).getByRole('button', { name: 'Join' })).toBeInTheDocument();
  });

  it('shows no "Join" when the community is by invitation (#1486)', () => {
    page.user = { cuid: 'u9' };
    render(
      <PublicCommunityPage
        community={{ ...community, join_policy: 'invitation' }}
        spaceGroups={[] as SpaceGroup[]}
        newestPosts={newestPosts}
      />,
    );

    expect(screen.queryByRole('button', { name: 'Join' })).toBeNull();
    expect(screen.queryByText(/join/i)).toBeNull();
  });

  it('lists the newest posts: a link to the post, an excerpt, the author, the space and the date', () => {
    page.user = null;
    renderPage();

    const first = screen.getByRole('article', { name: 'Welcome everyone' });
    expect(within(first).getByRole('link', { name: 'Welcome everyone' })).toHaveAttribute(
      'href',
      '/posts.show/tests/general/welcome-everyone',
    );
    expect(within(first).getByText('The first words of the post')).toBeInTheDocument();
    expect(within(first).getByText(/Ada Lovelace/)).toBeInTheDocument();
    expect(within(first).getByText(/ada$/)).toBeInTheDocument();
    expect(within(first).getByText(/General/)).toBeInTheDocument();
    expect(first.querySelector('time')).not.toBeNull();
    // The name of the author is not a link: the member pages are for members
    expect(within(first).getAllByRole('link')).toHaveLength(1);
  });

  it('names an author whose account is deleted', () => {
    page.user = null;
    renderPage();
    expect(screen.getByText('Deleted member')).toBeInTheDocument();
  });

  it('shows a message without posts', () => {
    page.user = null;
    renderPage([]);

    expect(screen.getByText('No posts yet.')).toBeInTheDocument();
  });

  it('shows nothing of the members: no count, no team, no member list', () => {
    page.user = null;
    renderPage();

    expect(screen.queryByText(/[0-9]+ members?/i)).toBeNull();
    expect(screen.queryByText(/team/i)).toBeNull();
  });
});
