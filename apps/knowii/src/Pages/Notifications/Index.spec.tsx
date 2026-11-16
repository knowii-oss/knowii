import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { CatchUp, Community } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { makeFollowing, makeNotification, page, person, spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationsProvider } from '@/Components/Notifications/NotificationsProvider';
import NotificationsPage from './Index';

const shared = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: shared.props, url: '/notifications' }),
}));
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params: Record<string, string>) => `/${name}/${Object.values(params).join('/')}`,
}));
// The layout holds the main heading
vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children, pageTitle }: { children: React.ReactNode; pageTitle?: string }) => (
    <div>
      <h1>{pageTitle}</h1>
      <main>{children}</main>
    </div>
  ),
}));

const communities = [
  { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
  { cuid: 'c2', slug: 'quiet', name: 'Quiet place' },
] as Community[];

const lines = (title: string): CatchUp => ({
  lines: [
    {
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
    },
  ],
  has_more: true,
});

describe('the page "Notifications" (#1476, design 5.1 and 5.5)', () => {
  let api: ReturnType<typeof spyOnNotificationApi>;
  let catchUp: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    shared.props = { communityCount: 2, catchUp: { since: '2026-11-01' } };
    api = spyOnNotificationApi();
    api.list.mockImplementation((input) =>
      Promise.resolve(input?.older ? page([makeNotification('old', { title: 'An old one' })]) : page([makeNotification('a')])),
    );
    api.following.mockResolvedValue(page([makeFollowing('f1')]));
    catchUp = vi
      .spyOn(knowiiApiClient.notifications, 'catchUp')
      .mockImplementation((community) =>
        Promise.resolve(
          community === 'c1'
            ? { ok: true as const, message: '', data: lines('Maintenance on Sunday') }
            : { ok: true as const, message: '', data: { lines: [], has_more: false } },
        ),
      );
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  // The layout gives the provider to every page of a member
  const renderPage = () =>
    render(
      <NotificationsProvider userCuid="u1">
        <NotificationsPage communities={communities} />
      </NotificationsProvider>,
    );

  it('has the heading "Notifications", then "Needs you", "Following" and "Earlier"', async () => {
    renderPage();

    expect(screen.getByRole('heading', { name: 'Notifications', level: 1 })).toBeInTheDocument();
    await screen.findByText('Title of a');
    const headings = screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent);
    expect(headings).toEqual(['Catch up', 'Needs you', 'Following', 'Earlier']);
  });

  it('reuses the lists of the panel: the same lines, the same buttons, no link to itself', async () => {
    renderPage();

    expect(await screen.findByText('Title of a')).toBeInTheDocument();
    expect(await screen.findByText(/Title of f1/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Done: Ben replied to your post: Title of a' })).toBeInTheDocument();
    // The one of the card, and the one of the heading "Following"
    expect(screen.getAllByRole('button', { name: 'I am caught up' })).toHaveLength(2);
    expect(screen.queryByRole('link', { name: 'Open the Notifications page' })).toBeNull();
  });

  it('loads "Earlier" when it opens, not before', async () => {
    renderPage();
    const user = userEvent.setup();
    await screen.findByText('Title of a');

    expect(api.list).not.toHaveBeenCalledWith(expect.objectContaining({ older: true }));
    await user.click(screen.getByRole('button', { name: 'Earlier' }));

    expect(await screen.findByText('An old one')).toBeInTheDocument();
    expect(api.list).toHaveBeenCalledWith({ older: true });
  });

  it('shows one "Catch up" card per community that has lines, with the name of the community', async () => {
    renderPage();

    const cards = await screen.findAllByRole('region', { name: /Catch up/ });
    expect(cards).toHaveLength(1);
    expect(within(cards[0] as HTMLElement).getByText('Knowii')).toBeInTheDocument();
    await waitFor(() => expect(catchUp).toHaveBeenCalledTimes(2));
    expect(catchUp).toHaveBeenCalledWith('c2', '2026-11-01', expect.anything());
  });

  it('shows no card without a date', async () => {
    shared.props = { communityCount: 2, catchUp: null };
    renderPage();

    await screen.findByText('Title of a');
    expect(screen.queryByRole('region', { name: /Catch up/ })).toBeNull();
    expect(catchUp).not.toHaveBeenCalled();
  });

  it('"Show all" moves the focus to the heading "Needs you"', async () => {
    renderPage();
    const user = userEvent.setup();

    await user.click(await screen.findByRole('link', { name: 'Show all' }));

    expect(screen.getByRole('heading', { name: 'Needs you' })).toHaveFocus();
  });

  it('"I am caught up" on the card hides it, clears "Following", says so, and moves the focus to the heading "Needs you"', async () => {
    renderPage();
    const user = userEvent.setup();
    await screen.findByText(/Title of f1/);

    await user.click(within(await screen.findByRole('region', { name: /Catch up/ })).getByRole('button', { name: 'I am caught up' }));

    await waitFor(() => expect(screen.queryByRole('region', { name: /Catch up/ })).toBeNull());
    expect(screen.queryByText(/Title of f1/)).toBeNull();
    expect(screen.getByText('Title of a')).toBeInTheDocument();
    expect(screen.getAllByRole('status').some((status) => status.textContent === 'You are caught up')).toBe(true);
    expect(screen.getByRole('heading', { name: 'Needs you' })).toHaveFocus();
  });

  it('has no automated violation (axe) with the card, the lists and "Earlier" open', async () => {
    const { container } = renderPage();
    const user = userEvent.setup();
    await screen.findByText(/Title of f1/);
    await screen.findByRole('region', { name: /Catch up/ });
    await user.click(screen.getByRole('button', { name: 'Earlier' }));
    await screen.findByText('An old one');

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
