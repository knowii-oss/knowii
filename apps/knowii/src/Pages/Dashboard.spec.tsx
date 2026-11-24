import { describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import type { Community } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import DashboardPage from './Dashboard';
import { emitSocketEvent } from '@/test-utils/fake-echo';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { visit: vi.fn(), reload: vi.fn() },
  usePage: () => ({ props: { legalPages: [] } }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params?: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
}));

const now = new Date();
const community = (cuid: string, name: string): Community =>
  ({
    cuid,
    name,
    slug: cuid,
    description: `About ${name}`,
    visibility: 'public',
    join_policy: 'open',
    is_member: true,
    created_at: now,
    updated_at: now,
  }) as unknown as Community;

describe('Dashboard', () => {
  it('lists only the communities of the person: a community that another person creates never shows', () => {
    render(
      <AppDataProvider>
        <DashboardPage communities={[community('c1', 'My own community')]} />
      </AppDataProvider>,
    );
    expect(screen.getByText('My own community')).toBeInTheDocument();

    // The channel of every public community does not exist any more; nothing that comes on it adds a card
    act(() => emitSocketEvent('communities', 'community.created', community('c2', 'Another community')));

    expect(screen.queryByText('Another community')).not.toBeInTheDocument();
    expect(screen.getByText('My own community')).toBeInTheDocument();
  });
});
