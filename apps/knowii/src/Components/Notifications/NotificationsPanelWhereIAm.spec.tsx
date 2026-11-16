import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { CatchUp, CatchUpLine } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { makeFollowing, makeNotification, page, person, spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationBell } from './NotificationBell';
import { NotificationsProvider } from './NotificationsProvider';

const shared = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: shared.props, url: '/' }),
}));
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params: Record<string, string>) => `/${name}/${Object.values(params).join('/')}`,
}));

const line = (title: string): CatchUpLine => ({
  kind: 'announcement',
  title,
  excerpt: 'The details',
  space: { cuid: 's1', slug: 'news', name: 'News' },
  author: person('Anna'),
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
  },
});

const answer = (lines: CatchUpLine[], hasMore = false): { ok: true; message: string; data: CatchUp } => ({
  ok: true,
  message: '',
  data: { lines, has_more: hasMore },
});

const renderBell = () =>
  render(
    <NotificationsProvider userCuid="u1">
      <NotificationBell />
    </NotificationsProvider>,
  );

const panel = () => screen.getByRole('region', { name: 'Notifications' });

async function openPanel() {
  const user = userEvent.setup();
  await user.click(screen.getByRole('button', { name: /^Notifications/ }));
  return user;
}

describe('the panel in "where I am" (#1476)', () => {
  let api: ReturnType<typeof spyOnNotificationApi>;
  let catchUp: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    shared.props = {
      communityCount: 1,
      community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
      catchUp: { since: '2026-11-01' },
    };
    api = spyOnNotificationApi();
    catchUp = vi.spyOn(knowiiApiClient.notifications, 'catchUp').mockResolvedValue(answer([line('Maintenance on Sunday')]));
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('ends with the link "Open the Notifications page"', async () => {
    renderBell();
    await openPanel();

    const link = await within(panel()).findByRole('link', { name: 'Open the Notifications page' });
    expect(link).toHaveAttribute('href', '/notifications.index/');
    const all = within(panel()).getAllByRole('link');
    expect(all[all.length - 1]).toBe(link);
  });

  it('calls the list of the lines older than 14 days "Earlier"', async () => {
    renderBell();
    await openPanel();

    expect(within(panel()).getByRole('button', { name: 'Earlier' })).toHaveAttribute('aria-expanded', 'false');
    expect(within(panel()).queryByText('Older')).toBeNull();
  });

  it('shows the "Catch up" card of the community of the page at the top of the panel, above "Needs you"', async () => {
    renderBell();
    await openPanel();

    const card = await within(panel()).findByRole('region', { name: 'Catch up' });
    expect(catchUp).toHaveBeenCalledWith('c1', '2026-11-01', expect.anything());
    expect(within(card).getByRole('heading', { name: 'Catch up', level: 3 })).toBeInTheDocument();
    const headings = within(panel())
      .getAllByRole('heading')
      .map((heading) => heading.textContent);
    expect(headings.indexOf('Catch up')).toBeLessThan(headings.indexOf('Needs you'));
  });

  it('shows no card when the page has no community, or no date', async () => {
    shared.props = { communityCount: 1, catchUp: { since: '2026-11-01' } };
    const { unmount } = renderBell();
    await openPanel();
    expect(within(panel()).queryByRole('region', { name: 'Catch up' })).toBeNull();
    expect(catchUp).not.toHaveBeenCalled();
    unmount();

    shared.props = { communityCount: 1, community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' }, catchUp: null };
    renderBell();
    await openPanel();
    expect(within(panel()).queryByRole('region', { name: 'Catch up' })).toBeNull();
    expect(catchUp).not.toHaveBeenCalled();
  });

  it('"Show all" moves the focus to the heading "Needs you"', async () => {
    catchUp.mockResolvedValue(answer([line('A')], true));
    renderBell();
    const user = await openPanel();

    await user.click(await within(panel()).findByRole('link', { name: 'Show all' }));

    expect(within(panel()).getByRole('heading', { name: 'Needs you' })).toHaveFocus();
  });

  it('"I am caught up" on the card clears the lines of "Following", keeps "Needs you", and moves the focus to the heading under the card', async () => {
    api.list.mockResolvedValue(page([makeNotification('a')]));
    api.following.mockResolvedValue(page([makeFollowing('f1')]));
    renderBell();
    const user = await openPanel();
    await within(panel()).findByText(/Title of f1/);

    await user.click(
      within(await within(panel()).findByRole('region', { name: 'Catch up' })).getByRole('button', { name: 'I am caught up' }),
    );

    expect(api.caughtUp).toHaveBeenCalledTimes(1);
    await waitFor(() => expect(within(panel()).queryByText(/Title of f1/)).toBeNull());
    expect(within(panel()).getByText('Title of a')).toBeInTheDocument();
    expect(within(panel()).queryByRole('region', { name: 'Catch up' })).toBeNull();
    expect(within(panel()).getByRole('heading', { name: 'Needs you' })).toHaveFocus();
    expect(
      within(panel())
        .getAllByRole('status')
        .some((status) => status.textContent === 'You are caught up'),
    ).toBe(true);
  });

  it('the button of "Following" hides the card too: the server forgot the date with the lines', async () => {
    api.following.mockResolvedValue(page([makeFollowing('f1')]));
    renderBell();
    const user = await openPanel();
    await within(panel()).findByText(/Title of f1/);
    await within(panel()).findByRole('region', { name: 'Catch up' });

    const buttons = within(panel()).getAllByRole('button', { name: 'I am caught up' });
    await user.click(buttons[buttons.length - 1] as HTMLElement);

    await waitFor(() => expect(within(panel()).queryByRole('region', { name: 'Catch up' })).toBeNull());
    expect(within(panel()).getByRole('heading', { name: 'Following' })).toHaveFocus();
  });
});
