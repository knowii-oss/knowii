import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Community, CommunityPermissions } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationsProvider } from '@/Components/Notifications/NotificationsProvider';
import CommunityPage from './Show';

const shared = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
  router: { visit: vi.fn() },
  usePage: () => ({ props: shared.props, url: '/community/knowii' }),
}));
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

const community = { cuid: 'c1', slug: 'knowii', name: 'Knowii', visibility: 'private', member_count: 3 } as Community;
const permissions = {} as CommunityPermissions;

describe('the home of a community and the "Catch up" card (#1476)', () => {
  beforeEach(() => {
    Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
    spyOnNotificationApi();
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const renderHome = () =>
    render(
      <NotificationsProvider userCuid="u1">
        <CommunityPage community={community} permissions={permissions} recentResources={[]} spaceGroups={[]} />
      </NotificationsProvider>,
    );

  it('shows the card as the first card of the home, for this community', async () => {
    shared.props = { communityCount: 1, catchUp: { since: '2026-11-01' } };
    const catchUp = vi.spyOn(knowiiApiClient.notifications, 'catchUp').mockResolvedValue({
      ok: true,
      message: '',
      data: {
        lines: [
          {
            kind: 'announcement',
            title: 'Maintenance on Sunday',
            excerpt: 'We move the servers.',
            space: { cuid: 's1', slug: 'news', name: 'News' },
            author: null,
            date: new Date('2026-11-14T09:00:00Z'),
            target: {
              post_slug: 'maintenance',
              post_cuid: 'p1',
              comment_cuid: null,
              chat_message_cuid: null,
              thread_cuid: null,
              resource_cuid: null,
              resource_slug: null,
              page_slug: null,
              event_cuid: null,
              event_slug: null,
            },
          },
        ],
        has_more: false,
      },
    });
    renderHome();

    const card = await screen.findByRole('region', { name: 'Catch up' });
    expect(catchUp).toHaveBeenCalledWith('c1', '2026-11-01', expect.anything());
    const cards = [...document.querySelectorAll('section, [role="region"]')];
    expect(cards[0]).toBe(card);
    expect(screen.getByRole('heading', { name: 'Knowii', level: 1 })).toBeInTheDocument();
  });

  it('shows no card without a date, and no card for a community with no line', async () => {
    shared.props = { communityCount: 1, catchUp: null };
    const catchUp = vi.spyOn(knowiiApiClient.notifications, 'catchUp');
    const { unmount } = renderHome();
    expect(screen.queryByRole('region', { name: 'Catch up' })).toBeNull();
    expect(catchUp).not.toHaveBeenCalled();
    unmount();

    shared.props = { communityCount: 1, catchUp: { since: '2026-11-01' } };
    catchUp.mockResolvedValue({ ok: true, message: '', data: { lines: [], has_more: false } });
    renderHome();
    await screen.findByRole('heading', { name: 'Recent Resources' });
    expect(screen.queryByRole('region', { name: 'Catch up' })).toBeNull();
  });
});
