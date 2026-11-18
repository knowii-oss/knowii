import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Community } from '@knowii/common';
import NoAccessPage from './NoAccess';

const layout = vi.hoisted(() => ({ title: '', breadcrumb: [] as Array<{ label: string; href?: string }> }));

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
  // The page is for a signed-in person only
  useTypedPage: () => ({ props: { auth: { user: { cuid: 'u9' } } } }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({
    children,
    browserPageTitle,
    breadcrumbItems,
  }: {
    children: React.ReactNode;
    browserPageTitle: string;
    breadcrumbItems?: Array<{ label: string; href?: string }>;
  }) => {
    layout.title = browserPageTitle;
    layout.breadcrumb = breadcrumbItems ?? [];
    return <div>{children}</div>;
  },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}${params ? `/${Object.values(params).join('/')}` : ''}`,
    writable: true,
  });
});

const community = {
  cuid: 'c1',
  name: 'Atlas Garden',
  slug: 'atlas-garden',
  description: 'A garden',
  visibility: 'private',
  is_member: false,
} as Community;

describe('The page without access (#1561)', () => {
  it('open community: the heading, the reason with the name of the community, and "Join"', () => {
    render(<NoAccessPage kind="space" community={{ ...community, join_policy: 'open' }} />);

    expect(screen.getByRole('heading', { level: 1, name: 'You do not have access to this page' })).toBeInTheDocument();
    expect(screen.getAllByRole('heading')).toHaveLength(1);
    expect(screen.getByText('This space is for the members of Atlas Garden.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Join' })).toBeInTheDocument();
    expect(screen.queryByText('This community is by invitation. Ask a member for an invitation.')).toBeNull();
    expect(screen.queryByRole('link', { name: 'Go to your communities' })).toBeNull();
    expect(layout.title).toBe('You do not have access to this page');
    expect(layout.breadcrumb).toEqual([expect.objectContaining({ label: 'Atlas Garden', href: '/communities.show/atlas-garden' })]);
  });

  it('a page of the community itself: "This page is for the members of"', () => {
    render(<NoAccessPage kind="community" community={{ ...community, join_policy: 'open' }} />);

    expect(screen.getByText('This page is for the members of Atlas Garden.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Join' })).toBeInTheDocument();
  });

  it('community by invitation: the reason, the invitation text, and a link to the public page, no "Join"', () => {
    render(<NoAccessPage kind="space" community={{ ...community, join_policy: 'invitation' }} />);

    expect(screen.getByRole('heading', { level: 1, name: 'You do not have access to this page' })).toBeInTheDocument();
    expect(screen.getByText('This space is for the members of Atlas Garden.')).toBeInTheDocument();
    expect(screen.getByText('This community is by invitation. Ask a member for an invitation.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Go to the community page' })).toHaveAttribute('href', '/communities.show/atlas-garden');
    expect(screen.queryByRole('button', { name: 'Join' })).toBeNull();
  });

  it('community that cannot be shown: the general text and a link to the communities of the person, nothing of a community', () => {
    const { container } = render(<NoAccessPage kind="space" community={null} />);

    expect(screen.getByRole('heading', { level: 1, name: 'You do not have access to this page' })).toBeInTheDocument();
    expect(
      screen.getByText('This page is for the members of a community. If a member gave you this link, ask that member for an invitation.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Go to your communities' })).toHaveAttribute('href', '/dashboard');
    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(screen.queryByRole('button')).toBeNull();
    expect(container.textContent).not.toContain('Atlas Garden');
    expect(layout.breadcrumb).toEqual([]);
  });
});
