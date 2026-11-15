import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { failure, makeNotification, page, person, spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationBell } from './NotificationBell';
import { NOTIFICATION_DEBOUNCE_MS, NotificationsProvider } from './NotificationsProvider';
import { SEEN_AFTER_MS, UNDO_MS } from './NotificationsPanel';

const shared = vi.hoisted(() => ({ communityCount: 1 }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: { communityCount: shared.communityCount }, url: '/' }),
}));
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params: Record<string, string>) => `/${name}/${Object.values(params).join('/')}`,
}));

const channel = 'user.u1';

function renderBell() {
  return render(
    <NotificationsProvider userCuid="u1">
      <NotificationBell />
    </NotificationsProvider>,
  );
}

async function openPanel(user = userEvent.setup()) {
  await user.click(screen.getByRole('button', { name: /^Notifications/ }));
  return user;
}

const panel = () => screen.getByRole('region', { name: 'Notifications' });
const lineOf = (title: string) => within(panel()).getByText(title).closest('li') as HTMLElement;
const linkOf = (title: string) => within(lineOf(title)).getByRole('link');

describe('the Notifications panel (#1484)', () => {
  let api: ReturnType<typeof spyOnNotificationApi>;

  beforeEach(() => {
    shared.communityCount = 1;
    api = spyOnNotificationApi();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  describe('opening and the states', () => {
    it('gives the focus to the heading, and the heading names the region', async () => {
      renderBell();
      await openPanel();

      expect(screen.getByRole('button', { name: 'Notifications' })).toHaveAttribute('aria-expanded', 'true');
      expect(document.getElementById('notifications-heading')).toHaveFocus();
      expect(screen.getByRole('heading', { name: 'Notifications' })).toBeInTheDocument();
      expect(within(panel()).getByRole('heading', { name: 'Needs you' })).toBeInTheDocument();
    });

    it('shows 4 skeleton lines and aria-busy while it loads', async () => {
      api.list.mockReturnValue(new Promise(() => undefined));
      renderBell();
      await openPanel();

      const list = within(panel()).getByRole('list', { hidden: true });
      expect(list).toHaveAttribute('aria-busy', 'true');
      expect(list.querySelectorAll('li')).toHaveLength(4);
    });

    it('shows the empty state', async () => {
      renderBell();
      await openPanel();

      expect(await screen.findByText('Nothing needs you.')).toBeInTheDocument();
      expect(screen.getByText('Everything is quiet. That is fine.')).toBeInTheDocument();
    });

    it('shows an error with "Retry", which gets the focus and loads again', async () => {
      api.list.mockResolvedValueOnce(failure());
      renderBell();
      const user = await openPanel();

      expect(await screen.findByText('We could not load your notifications.')).toBeInTheDocument();
      const retry = screen.getByRole('button', { name: 'Retry' });
      expect(retry).toHaveFocus();

      api.list.mockResolvedValue(page([makeNotification('a')]));
      await user.click(retry);
      expect(await screen.findByText('Title of a')).toBeInTheDocument();
    });

    it('closes with Escape and gives the focus back to the bell', async () => {
      renderBell();
      const user = await openPanel();
      await screen.findByText('Nothing needs you.');

      await user.keyboard('{Escape}');

      expect(screen.queryByRole('region', { name: 'Notifications' })).toBeNull();
      expect(screen.getByRole('button', { name: 'Notifications' })).toHaveFocus();
    });

    it('closes with a click outside, and Tab leaves the panel without closing it', async () => {
      renderBell();
      const user = await openPanel();
      await screen.findByText('Nothing needs you.');

      await user.tab();
      expect(screen.getByRole('region', { name: 'Notifications' })).toBeInTheDocument();

      await user.click(document.body);
      expect(screen.queryByRole('region', { name: 'Notifications' })).toBeNull();
    });
  });

  describe('a line', () => {
    it('shows the avatar, the sentence, the title, the excerpt, the space and a time with its full date', async () => {
      api.list.mockResolvedValue(page([makeNotification('a', { updated_at: new Date('2026-11-15T10:00:00Z') })]));
      renderBell();
      await openPanel();

      const line = await within(panel()).findByRole('listitem');
      expect(within(line).getByRole('link', { name: /Ben replied to your post/ })).toHaveAttribute(
        'href',
        '/posts.show/knowii/open/post-a#comment-comment-a',
      );
      expect(within(line).getByText('Title of a')).toBeInTheDocument();
      expect(within(line).getByText('Excerpt of a')).toHaveClass('line-clamp-2');
      expect(within(line).getByText('Open discussion')).toBeInTheDocument();
      expect(line.querySelector('time')).toHaveAttribute('datetime', '2026-11-15T10:00:00.000Z');
      expect(line.textContent).toMatch(/, Nov 15, 2026/);
    });

    it('builds the sentence for 1, 2, 3 and 5 authors, and for a deleted author', async () => {
      const [anna, ben, carl] = [person('Anna'), person('Ben'), person('Carl')];
      api.list.mockResolvedValue(
        page([
          makeNotification('one', { actors: [anna], actor: anna, title: 't1' }),
          makeNotification('two', { kind: 'comment_reply', actors: [anna, ben], title: 't2' }),
          makeNotification('three', { actors: [anna, ben, carl], title: 't3' }),
          makeNotification('five', { actors: [anna, ben, carl], others_count: 2, title: 't5' }),
          makeNotification('gone', { actors: [], actor: null, title: 't6' }),
          makeNotification('mention', { kind: 'mention', actors: [person('Marc')], title: null }),
        ]),
      );
      renderBell();
      await openPanel();

      await screen.findByText('Anna replied to your post');
      expect(screen.getByText('Anna and Ben replied to your comment')).toBeInTheDocument();
      expect(screen.getByText('Anna, Ben and Carl replied to your post')).toBeInTheDocument();
      expect(screen.getByText('Anna, Ben, Carl and 2 others replied to your post')).toBeInTheDocument();
      expect(screen.getByText('Deleted member replied to your post')).toBeInTheDocument();
      expect(screen.getByText('Marc mentioned you in Open discussion')).toBeInTheDocument();
    });

    it('shows the name of the community only when the member is in more than one', async () => {
      api.list.mockResolvedValue(page([makeNotification('a')]));
      shared.communityCount = 2;
      const { unmount } = renderBell();
      await openPanel();
      expect(await screen.findByText('Open discussion · Knowii')).toBeInTheDocument();
      unmount();

      shared.communityCount = 1;
      renderBell();
      await openPanel();
      expect(await screen.findByText('Open discussion')).toBeInTheDocument();
      expect(screen.queryByText(/· Knowii/)).toBeNull();
    });

    it('marks a line that was not seen with the word "New" and bold text, never by color alone', async () => {
      api.list.mockResolvedValue(page([makeNotification('fresh'), makeNotification('old', { seen_at: new Date('2026-11-14T10:00:00Z') })]));
      renderBell();
      await openPanel();

      await screen.findByText('Title of fresh');
      const fresh = lineOf('Title of fresh');
      expect(within(fresh).getByText('New')).toBeInTheDocument();
      expect(within(fresh).getByRole('link')).toHaveClass('font-bold');
      expect(within(lineOf('Title of old')).queryByText('New')).toBeNull();
    });

    it('opens the item: marks the line read and closes the panel', async () => {
      api.list.mockResolvedValue(page([makeNotification('a')]));
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of a');
      // A click on a link of jsdom does not navigate
      const link = linkOf('Title of a');
      link.addEventListener('click', (event) => event.preventDefault());

      await user.click(link);

      expect(api.read).toHaveBeenCalledWith('a');
      expect(screen.queryByRole('region', { name: 'Notifications' })).toBeNull();
    });

    it('addresses the chat message, its thread, and the comment of a resource', async () => {
      const chat = makeNotification('chat', {
        kind: 'mention',
        title: null,
        excerpt: 'In the chat',
        target: {
          post_slug: null,
          comment_cuid: null,
          chat_message_cuid: 'm1',
          thread_cuid: 'root',
          resource_cuid: null,
          resource_slug: null,
        },
      });
      const resource = makeNotification('res', {
        title: null,
        excerpt: 'On a resource',
        target: {
          post_slug: null,
          comment_cuid: 'k9',
          chat_message_cuid: null,
          thread_cuid: null,
          resource_cuid: 'r1',
          resource_slug: 'the-resource',
        },
      });
      api.list.mockResolvedValue(page([chat, resource]));
      renderBell();
      await openPanel();

      await screen.findByText('In the chat');
      const links = within(panel()).getAllByRole('link');
      expect(links[0]).toHaveAttribute('href', '/spaces.show/knowii/open?thread=root#message-m1');
      expect(links[1]).toHaveAttribute('href', '/resources.show/knowii/open/the-resource#comment-k9');
    });
  });

  describe('seen', () => {
    it('marks the lines seen after one second in the open panel: one request for all of them, and the count is loaded again', async () => {
      api.list.mockResolvedValue(page([makeNotification('a'), makeNotification('b'), makeNotification('c', { seen_at: new Date() })]));
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });
      expect(screen.getByText('Title of a')).toBeInTheDocument();
      const countsBefore = api.count.mock.calls.length;

      await act(async () => {
        await vi.advanceTimersByTimeAsync(SEEN_AFTER_MS - 100);
      });
      expect(api.seen).not.toHaveBeenCalled();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(200);
      });
      expect(api.seen).toHaveBeenCalledTimes(1);
      expect(api.seen).toHaveBeenCalledWith(['a', 'b']);
      expect(api.count.mock.calls.length).toBe(countsBefore + 1);
      // "New" stays while the panel is open: the member sees which lines were new
      expect(within(lineOf('Title of a')).getByText('New')).toBeInTheDocument();
    });

    it('does not mark a line that was not on screen for one second', async () => {
      const visible = new Map<Element, (entries: Array<Partial<IntersectionObserverEntry>>) => void>();
      class FakeObserver {
        constructor(private readonly callback: (entries: Array<Partial<IntersectionObserverEntry>>) => void) {}
        observe(element: Element) {
          visible.set(element, this.callback);
        }
        disconnect() {
          visible.clear();
        }
        unobserve() {
          // Not needed
        }
      }
      vi.stubGlobal('IntersectionObserver', FakeObserver);
      api.list.mockResolvedValue(page([makeNotification('a'), makeNotification('b')]));
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });

      // Only the first line is on screen
      const [first] = [...visible.entries()].filter(([element]) => element.getAttribute('data-notification-cuid') === 'a');
      expect(first).toBeDefined();
      act(() => first?.[1]([{ target: first[0], isIntersecting: true }]));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(SEEN_AFTER_MS + 100);
      });

      expect(api.seen).toHaveBeenCalledTimes(1);
      expect(api.seen).toHaveBeenCalledWith(['a']);
      vi.unstubAllGlobals();
    });
  });

  describe('"Done"', () => {
    const three = () => page([makeNotification('a'), makeNotification('b'), makeNotification('c')]);

    it('names the button after the line, marks the row read, removes the line and says "Done. Undo"', async () => {
      api.list.mockResolvedValue(three());
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of b');

      await user.click(within(lineOf('Title of b')).getByRole('button', { name: 'Done: Ben replied to your post: Title of b' }));

      expect(api.read).toHaveBeenCalledWith('b');
      await waitFor(() => expect(within(panel()).queryByText('Title of b')).toBeNull());
      const status = within(panel())
        .getByText(/Done\./)
        .closest('[role="status"]') as HTMLElement;
      expect(status).toHaveAttribute('aria-live', 'polite');
      expect(within(status).getByRole('button', { name: 'Undo' })).toBeInTheDocument();
    });

    it('moves the focus to the next line, else the line before, else the heading', async () => {
      api.list.mockResolvedValue(three());
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of b');

      // The middle line: the next one
      await user.click(within(lineOf('Title of b')).getByRole('button', { name: /^Done:/ }));
      await waitFor(() => expect(linkOf('Title of c')).toHaveFocus());
      // The last line: the line before
      await user.click(within(lineOf('Title of c')).getByRole('button', { name: /^Done:/ }));
      await waitFor(() => expect(linkOf('Title of a')).toHaveFocus());
      // The only line: the heading
      await user.click(within(lineOf('Title of a')).getByRole('button', { name: /^Done:/ }));
      await waitFor(() => expect(document.getElementById('notifications-heading')).toHaveFocus());
    });

    it('never uses disabled: a second press while the first waits is ignored, with aria-busy', async () => {
      api.list.mockResolvedValue(three());
      let answer: () => void = () => undefined;
      api.read.mockImplementation(
        (cuid) =>
          new Promise((resolve) => {
            answer = () => resolve({ ok: true, message: '', data: makeNotification(cuid, { read_at: new Date() }) });
          }),
      );
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of a');

      const button = within(lineOf('Title of a')).getByRole('button', { name: /^Done:/ });
      await user.click(button);
      expect(button).not.toBeDisabled();
      expect(button).toHaveAttribute('aria-busy', 'true');
      expect(button).toHaveAttribute('aria-disabled', 'true');
      await user.click(button);
      expect(api.read).toHaveBeenCalledTimes(1);

      await act(async () => answer());
      await waitFor(() => expect(within(panel()).queryByText('Title of a')).toBeNull());
    });

    it('says so, with role alert, when the server refuses, and keeps the line', async () => {
      api.list.mockResolvedValue(three());
      api.read.mockResolvedValue(failure());
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of a');

      await user.click(within(lineOf('Title of a')).getByRole('button', { name: /^Done:/ }));

      expect(await screen.findByRole('alert')).toHaveTextContent('We could not mark it as done.');
      expect(within(panel()).getByText('Title of a')).toBeInTheDocument();
    });

    it('"Undo" puts the line back at its place and gives it the focus', async () => {
      api.list.mockResolvedValue(three());
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of b');
      await user.click(within(lineOf('Title of b')).getByRole('button', { name: /^Done:/ }));
      await waitFor(() => expect(within(panel()).queryByText('Title of b')).toBeNull());

      await user.click(within(panel()).getByRole('button', { name: 'Undo' }));

      expect(api.unread).toHaveBeenCalledWith('b');
      await waitFor(() => expect(linkOf('Title of b')).toHaveFocus());
      expect(
        within(panel())
          .getAllByRole('listitem')
          .map((item) => item.querySelector('p.truncate')?.textContent),
      ).toEqual(['Title of a', 'Title of b', 'Title of c']);
      expect(within(panel()).queryByRole('button', { name: 'Undo' })).toBeNull();
    });

    it('"Undo" lasts 8 seconds', async () => {
      api.list.mockResolvedValue(three());
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });
      fireEvent.click(within(lineOf('Title of a')).getByRole('button', { name: /^Done:/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });
      expect(within(panel()).getByRole('button', { name: 'Undo' })).toBeInTheDocument();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(UNDO_MS - 500);
      });
      expect(within(panel()).getByRole('button', { name: 'Undo' })).toBeInTheDocument();
      await act(async () => {
        await vi.advanceTimersByTimeAsync(1000);
      });
      expect(within(panel()).queryByRole('button', { name: 'Undo' })).toBeNull();
    });

    it('loads the list again when a newer notification exists (business error of "Undo")', async () => {
      api.list.mockResolvedValue(three());
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of a');
      await user.click(within(lineOf('Title of a')).getByRole('button', { name: /^Done:/ }));
      await waitFor(() => expect(within(panel()).queryByText('Title of a')).toBeNull());
      const loads = api.list.mock.calls.length;
      api.unread.mockResolvedValue({ ok: false, status: 422, message: 'A newer notification about this already exists', errors: {} });
      api.list.mockResolvedValue(page([makeNotification('z'), makeNotification('b'), makeNotification('c')]));

      await user.click(within(panel()).getByRole('button', { name: 'Undo' }));

      await waitFor(() => expect(api.list.mock.calls.length).toBe(loads + 1));
      expect(await screen.findByText('Title of z')).toBeInTheDocument();
      expect(within(panel()).queryByText('Title of a')).toBeNull();
      expect(within(panel()).getByText(/A newer notification about this exists/)).toBeInTheDocument();
    });
  });

  describe('more lines', () => {
    it('"Load older" loads the next page, gives the focus to the first new line and says how many', async () => {
      api.list.mockResolvedValueOnce(page([makeNotification('a'), makeNotification('b')], 2));
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of b');
      api.list.mockResolvedValueOnce(page([makeNotification('c'), makeNotification('d'), makeNotification('e')], null));

      await user.click(within(panel()).getByRole('button', { name: 'Load older' }));

      expect(api.list).toHaveBeenLastCalledWith({ older: false, offset: 2, limit: 20 });
      await waitFor(() => expect(linkOf('Title of c')).toHaveFocus());
      expect(within(panel()).getByText('3 more loaded').closest('[role="status"]')).toHaveAttribute('aria-live', 'polite');
      expect(within(panel()).queryByRole('button', { name: 'Load older' })).toBeNull();
    });

    it('keeps "Older" closed, and loads it when the member opens it the first time', async () => {
      api.list.mockImplementation((input) =>
        Promise.resolve(input?.older ? page([makeNotification('old', { title: 'An old one' })]) : page([makeNotification('a')])),
      );
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of a');

      const disclosure = within(panel()).getByRole('button', { name: 'Older' });
      expect(disclosure).toHaveAttribute('aria-expanded', 'false');
      expect(api.list).not.toHaveBeenCalledWith(expect.objectContaining({ older: true }));

      await user.click(disclosure);

      expect(disclosure).toHaveAttribute('aria-expanded', 'true');
      expect(await screen.findByText('An old one')).toBeInTheDocument();
      expect(api.list).toHaveBeenCalledWith({ older: true });
      // Never counted and never marked seen: the rows are older than 14 days
      expect(within(lineOf('An old one')).queryByText('New')).toBeNull();
    });
  });

  describe('events while the panel is open', () => {
    it('does not add an arriving line by itself: "1 new. Show" loads it', async () => {
      api.list.mockResolvedValue(page([makeNotification('a'), makeNotification('b')]));
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });

      api.list.mockResolvedValue(page([makeNotification('c'), makeNotification('a'), makeNotification('b')]));
      emitSocketEvent(channel, 'notifications.changed', { notification_cuid: 'c', community_cuid: 'c1' });
      await act(async () => {
        await vi.advanceTimersByTimeAsync(NOTIFICATION_DEBOUNCE_MS + 50);
      });

      const show = within(panel()).getByRole('button', { name: '1 new. Show' });
      expect(within(panel()).queryByText('Title of c')).toBeNull();

      fireEvent.click(show);
      expect(within(panel()).getByText('Title of c')).toBeInTheDocument();
      expect(within(panel()).queryByRole('button', { name: /new\. Show/ })).toBeNull();
      expect(linkOf('Title of c')).toHaveFocus();
    });

    it('keeps a line that has the focus when another device marks it done, until the focus leaves it', async () => {
      api.list.mockResolvedValue(page([makeNotification('a'), makeNotification('b')]));
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });
      await screen.findByText('Title of a');
      // The panel gives the focus to its heading once more 50 ms after it opens (the drawer takes it first)
      await act(async () => {
        await vi.advanceTimersByTimeAsync(100);
      });
      act(() => linkOf('Title of a').focus());

      // Another device: the row "a" is read, so the server does not list it any more
      api.list.mockResolvedValue(page([makeNotification('b')]));
      emitSocketEvent(channel, 'notifications.changed', { notification_cuid: 'a', community_cuid: 'c1' });
      await act(async () => {
        await vi.advanceTimersByTimeAsync(NOTIFICATION_DEBOUNCE_MS + 50);
      });

      expect(within(panel()).getByText('Title of a')).toBeInTheDocument();
      expect(within(lineOf('Title of a')).getByText('Done on another device')).toBeInTheDocument();
      expect(linkOf('Title of a')).toHaveFocus();

      act(() => linkOf('Title of b').focus());
      expect(within(panel()).queryByText('Title of a')).toBeNull();
      expect(within(panel()).getByText('Title of b')).toBeInTheDocument();
    });

    it('removes at once a line that left and had no focus', async () => {
      api.list.mockResolvedValue(page([makeNotification('a'), makeNotification('b')]));
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      fireEvent.click(screen.getByRole('button', { name: /^Notifications/ }));
      await act(async () => {
        await vi.advanceTimersByTimeAsync(0);
      });

      api.list.mockResolvedValue(page([makeNotification('b')]));
      emitSocketEvent(channel, 'notifications.changed', { notification_cuid: 'a', community_cuid: 'c1' });
      await act(async () => {
        await vi.advanceTimersByTimeAsync(NOTIFICATION_DEBOUNCE_MS + 50);
      });

      expect(within(panel()).queryByText('Title of a')).toBeNull();
    });
  });

  describe('accessibility', () => {
    it('has no automated violation (axe) with lines, the status text and "Older" open', async () => {
      api.list.mockImplementation((input) =>
        Promise.resolve(
          input?.older
            ? page([makeNotification('old', { title: 'An old one' })])
            : page([makeNotification('a'), makeNotification('b', { seen_at: new Date() })], 2),
        ),
      );
      renderBell();
      const user = await openPanel();
      await screen.findByText('Title of a');
      await user.click(within(panel()).getByRole('button', { name: 'Older' }));
      await screen.findByText('An old one');
      await user.click(within(lineOf('Title of b')).getByRole('button', { name: /^Done:/ }));
      await screen.findByRole('button', { name: 'Undo' });

      // jsdom has no layout: the contrast is checked in the browser (both themes)
      const results = await axe.run(panel(), { rules: { 'color-contrast': { enabled: false } } });

      expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.html).join(' | ')}`)).toEqual([]);
    });

    it('has no automated violation for the loading, empty and error states', async () => {
      api.list.mockResolvedValueOnce(failure());
      renderBell();
      await openPanel();
      await screen.findByText('We could not load your notifications.');

      const results = await axe.run(panel(), { rules: { 'color-contrast': { enabled: false } } });

      expect(results.violations).toEqual([]);
    });
  });
});

describe('the Notifications panel on a phone (#1484)', () => {
  beforeEach(() => {
    shared.communityCount = 1;
    spyOnNotificationApi().list.mockResolvedValue(page([makeNotification('a')]));
    vi.stubGlobal('matchMedia', (query: string) => ({
      matches: query.includes('max-width'),
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }));
  });
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('opens the Drawer component, with the focus on the heading, and closes with its button', async () => {
    renderBell();
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'Notifications' }));

    const dialog = await screen.findByRole('dialog', { name: 'Notifications' });
    expect(await within(dialog).findByText('Title of a')).toBeInTheDocument();
    await waitFor(() => expect(document.getElementById('notifications-heading')).toHaveFocus());

    // The return of the focus to the bell is the job of useReturnFocus (tested on its own, and in a real browser: jsdom
    // keeps the closing transition)
    await user.click(within(dialog).getByRole('button', { name: 'Close notifications' }));
    expect(screen.getByRole('button', { name: 'Notifications' })).toHaveAttribute('aria-expanded', 'false');
  });
});
