import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { CatchUp, CatchUpLine } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { person, spyOnNotificationApi } from '@/test-utils/notifications';
import { CatchUpCard } from './CatchUpCard';
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

const community = { cuid: 'c1', slug: 'knowii', name: 'Knowii' };

const line = (kind: CatchUpLine['kind'], title: string | null, excerpt: string, changes: Partial<CatchUpLine> = {}): CatchUpLine => ({
  kind,
  title,
  excerpt,
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
    event_cuid: null,
    event_slug: null,
  },
  ...changes,
});

const answer = (lines: CatchUpLine[], hasMore = false): { ok: true; message: string; data: CatchUp } => ({
  ok: true,
  message: '',
  data: { lines, has_more: hasMore },
});

describe('the "Catch up" card (#1476, design 5.5 and 9.7)', () => {
  let catchUp: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    shared.props = { catchUp: { since: '2026-11-01' }, communityCount: 1 };
    spyOnNotificationApi();
    catchUp = vi
      .spyOn(knowiiApiClient.notifications, 'catchUp')
      .mockResolvedValue(answer([line('announcement', 'Maintenance on Sunday', 'We move the servers.')]));
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('is a region with the heading "Catch up": not a dialog and not a live pop-up', async () => {
    render(<CatchUpCard community={community} />);

    const region = await screen.findByRole('region', { name: 'Catch up' });
    expect(within(region).getByRole('heading', { name: 'Catch up', level: 2 })).toBeInTheDocument();
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(region).not.toHaveAttribute('aria-live');
    expect(catchUp).toHaveBeenCalledWith('c1', '2026-11-01', expect.anything());
  });

  it('shows each line as one link of 2 lines of text, with the title then the excerpt', async () => {
    catchUp.mockResolvedValue(
      answer([
        line('announcement', 'Maintenance on Sunday', 'We move the servers.'),
        line('needs_you', null, 'Ben replied to your post', { target: { ...line('needs_you', null, '').target, post_slug: 'other' } }),
      ]),
    );
    render(<CatchUpCard community={community} />);

    const links = await screen.findAllByRole('link');
    expect(links).toHaveLength(2);
    expect(links[0]).toHaveAttribute('href', '/posts.show/knowii/news/maintenance');
    expect(links[0]).toHaveTextContent('Maintenance on Sunday');
    expect(links[0]).toHaveTextContent('We move the servers.');
    // Without a title the excerpt comes first and the space and the date come under it
    expect(links[1]).toHaveTextContent('Ben replied to your post');
    expect(links[1]).toHaveTextContent('News,');
  });

  it('shows no count of missed things, and no "Show all" when nothing more exists', async () => {
    render(<CatchUpCard community={community} />);

    const region = await screen.findByRole('region', { name: 'Catch up' });
    expect(region.textContent).not.toMatch(/\d+ (new|missed|unread)/i);
    expect(within(region).queryByRole('link', { name: 'Show all' })).toBeNull();
  });

  it('shows nothing without a date, for a visitor, and for a community with no line', async () => {
    shared.props = { catchUp: null };
    const { unmount } = render(<CatchUpCard community={community} />);
    expect(screen.queryByRole('region', { name: 'Catch up' })).toBeNull();
    expect(catchUp).not.toHaveBeenCalled();
    unmount();

    shared.props = { catchUp: { since: '2026-11-01' } };
    catchUp.mockResolvedValue(answer([]));
    render(<CatchUpCard community={community} />);
    await waitFor(() => expect(catchUp).toHaveBeenCalled());
    expect(screen.queryByRole('region', { name: 'Catch up' })).toBeNull();
  });

  it('shows nothing when the answer fails', async () => {
    catchUp.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    render(<CatchUpCard community={community} />);

    await waitFor(() => expect(catchUp).toHaveBeenCalled());
    expect(screen.queryByRole('region', { name: 'Catch up' })).toBeNull();
  });

  it('names the community under the heading when the page holds several cards', async () => {
    render(<CatchUpCard community={community} showCommunity headingLevel={2} />);

    const region = await screen.findByRole('region', { name: /Catch up Knowii/ });
    expect(within(region).getByText('Knowii')).toBeInTheDocument();
  });

  it('offers "Show all" when more exists: a link to the page that opens the panel', async () => {
    catchUp.mockResolvedValue(answer([line('announcement', 'A', 'B')], true));
    const onShowAll = vi.fn();
    render(<CatchUpCard community={community} onShowAll={onShowAll} />);

    const link = await screen.findByRole('link', { name: 'Show all' });
    expect(link).toHaveAttribute('href', '/notifications.index/');
    await userEvent.click(link);
    expect(onShowAll).toHaveBeenCalledTimes(1);
  });

  it('opens the panel of the bell with "Show all" on the home', async () => {
    catchUp.mockResolvedValue(answer([line('announcement', 'A', 'B')], true));
    render(
      <NotificationsProvider userCuid="u1">
        <NotificationBell />
        <CatchUpCard community={community} />
      </NotificationsProvider>,
    );

    expect(screen.queryByRole('region', { name: 'Notifications' })).toBeNull();
    await userEvent.click(await screen.findByRole('link', { name: 'Show all' }));

    expect(await screen.findByRole('region', { name: 'Notifications' })).toBeInTheDocument();
  });

  describe('"I am caught up"', () => {
    it('hides the card, says "You are caught up", and moves the focus to the main heading when there is no list', async () => {
      const api = spyOnNotificationApi();
      const user = userEvent.setup();
      render(
        <>
          <h1>Knowii</h1>
          <CatchUpCard community={community} />
        </>,
      );

      await user.click(await screen.findByRole('button', { name: 'I am caught up' }));

      expect(api.caughtUp).toHaveBeenCalledTimes(1);
      await waitFor(() => expect(screen.queryByRole('region', { name: 'Catch up' })).toBeNull());
      expect(screen.getByRole('status')).toHaveTextContent('You are caught up');
      expect(screen.getByRole('heading', { name: 'Knowii', level: 1 })).toHaveFocus();
    });

    it('hides every card of the page at once', async () => {
      spyOnNotificationApi();
      const user = userEvent.setup();
      render(
        <>
          <CatchUpCard community={community} />
          <CatchUpCard community={{ cuid: 'c2', slug: 'other', name: 'Other' }} />
        </>,
      );

      const buttons = await screen.findAllByRole('button', { name: 'I am caught up' });
      expect(buttons).toHaveLength(2);
      await user.click(buttons[0] as HTMLElement);

      await waitFor(() => expect(screen.queryAllByRole('region', { name: /Catch up/ })).toHaveLength(0));
    });

    it('calls the function of the list under the card, in place of the main heading', async () => {
      spyOnNotificationApi();
      const onCaughtUp = vi.fn();
      const user = userEvent.setup();
      render(<CatchUpCard community={community} onCaughtUp={onCaughtUp} />);

      await user.click(await screen.findByRole('button', { name: 'I am caught up' }));

      await waitFor(() => expect(onCaughtUp).toHaveBeenCalledTimes(1));
    });

    it('keeps the card and says so when the request fails, and the button keeps the focus (no disabled)', async () => {
      const api = spyOnNotificationApi();
      api.caughtUp.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
      const user = userEvent.setup();
      render(<CatchUpCard community={community} />);

      const button = await screen.findByRole('button', { name: 'I am caught up' });
      await user.click(button);

      expect(await screen.findByRole('alert')).toHaveTextContent('We could not clear it. Try again.');
      expect(screen.getByRole('region', { name: 'Catch up' })).toBeInTheDocument();
      expect(button).not.toBeDisabled();
      expect(button).toHaveFocus();
    });

    it('sends one request when the button is pressed twice', async () => {
      const api = spyOnNotificationApi();
      let release: (value: { ok: true; message: string; data: { marked: number } }) => void = () => undefined;
      api.caughtUp.mockReturnValue(new Promise((resolve) => (release = resolve)));
      const user = userEvent.setup();
      render(<CatchUpCard community={community} />);

      const button = await screen.findByRole('button', { name: 'I am caught up' });
      await user.click(button);
      await user.click(button);
      expect(button).toHaveAttribute('aria-disabled', 'true');
      release({ ok: true, message: '', data: { marked: 0 } });

      await waitFor(() => expect(api.caughtUp).toHaveBeenCalledTimes(1));
    });
  });

  it('has no automated violation (axe), with the "Show all" link and the status text', async () => {
    catchUp.mockResolvedValue(
      answer([line('announcement', 'Maintenance on Sunday', 'We move the servers.'), line('thread', 'A busy thread', '')], true),
    );
    const { container } = render(<CatchUpCard community={community} showCommunity />);
    await screen.findByRole('region', { name: /Catch up/ });

    const results = await axe.run(container, { rules: { region: { enabled: false } } });

    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
