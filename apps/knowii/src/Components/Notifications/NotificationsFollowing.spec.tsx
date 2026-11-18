import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { failure, makeFollowing, makeNotification, page, spyOnFollowApi, spyOnNotificationApi } from '@/test-utils/notifications';
import { NotificationBell } from './NotificationBell';
import { NotificationsProvider } from './NotificationsProvider';
import { UNDO_MS } from './NotificationsPanel';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  usePage: () => ({ props: { communityCount: 1 }, url: '/' }),
}));
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params: Record<string, string>) => `/${name}/${Object.values(params).join('/')}`,
}));

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
const followingSection = () => within(panel()).getByRole('region', { name: 'Following' });
const lineOf = (text: string | RegExp) => within(followingSection()).getByText(text).closest('li') as HTMLElement;

describe('"Following" in the Notifications panel (#1497)', () => {
  let api: ReturnType<typeof spyOnNotificationApi>;
  let follow: ReturnType<typeof spyOnFollowApi>;

  beforeEach(() => {
    api = spyOnNotificationApi();
    follow = spyOnFollowApi();
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  describe('the heading and the lines', () => {
    it('shows "Following" under "Needs you", with its own empty text', async () => {
      renderBell();
      await openPanel();

      const headings = await within(panel()).findAllByRole('heading', { level: 3 });
      expect(headings.map((heading) => heading.textContent)).toEqual(['Needs you', 'Following', expect.stringContaining('Earlier')]);
      expect(await within(panel()).findByText('Nothing new in what you follow.')).toBeInTheDocument();
      expect(within(panel()).queryByRole('button', { name: 'I am caught up' })).toBeNull();
    });

    it('asks for the level 2 lines only, and never mixes them with "Needs you"', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      api.following.mockResolvedValue(page([makeFollowing('f1', { title: 'Following title' })]));
      renderBell();
      await openPanel();

      expect(await within(followingSection()).findByText(/Following title/)).toBeInTheDocument();
      expect(within(followingSection()).queryByText('Needs you title')).toBeNull();
      expect(api.following).toHaveBeenCalledOnce();
      expect(api.list).toHaveBeenCalledWith({ older: false });
    });

    it('builds one sentence for each kind from the live counts', async () => {
      const course = { cuid: 's2', slug: 'course', name: 'Notes course' };
      api.following.mockResolvedValue(
        page([
          makeFollowing('a', { title: 'Notes workflow', items_count: 3 }),
          makeFollowing('b', { kind: 'space_post', reason: 'space_everything', items_count: 1, title: 'A new idea' }),
          makeFollowing('c', { kind: 'space_post', reason: 'space_everything', items_count: 4, title: 'A new idea' }),
          makeFollowing('d', { kind: 'space_chat', reason: 'space_everything', items_count: 6, title: null }),
          makeFollowing('e', { kind: 'lesson_new', reason: 'started_course', items_count: 1, title: 'First steps', space: course }),
        ]),
      );
      renderBell();
      await openPanel();

      const list = await within(followingSection()).findByRole('list');
      const sentences = within(list)
        .getAllByRole('link')
        .map((link) => link.textContent?.replace(/New\s*/, '').trim());
      expect(sentences).toEqual([
        '3 new replies in Notes workflow',
        'Ben posted in Open discussion: A new idea',
        '4 new posts in Open discussion',
        'New messages in Open discussion',
        'Lesson First steps of Notes course is out',
      ]);
    });

    it('shows no title and no excerpt on a line of several new posts or lessons, nor in its names (#1509)', async () => {
      api.following.mockResolvedValue(
        page([
          makeFollowing('many', {
            kind: 'space_post',
            reason: 'space_everything',
            items_count: 3,
            title: 'Only one of them',
            excerpt: 'Excerpt of one',
          }),
          makeFollowing('lessons', {
            kind: 'lesson_new',
            reason: 'started_course',
            items_count: 2,
            title: 'Lesson two',
            excerpt: 'Excerpt of two',
          }),
          makeFollowing('one', {
            kind: 'space_post',
            reason: 'space_everything',
            items_count: 1,
            title: 'Single post',
            excerpt: 'Excerpt of single',
          }),
          makeFollowing('thread', { items_count: 3, title: 'Thread title', excerpt: 'Excerpt of thread' }),
        ]),
      );
      renderBell();
      await openPanel();

      const many = lineOf(/3 new posts in Open discussion/);
      expect(within(many).queryByText(/Only one of them/)).toBeNull();
      expect(within(many).queryByText(/Excerpt of one/)).toBeNull();
      expect(within(many).getByText('Open discussion')).toBeInTheDocument();
      expect(within(many).getByRole('button', { name: 'More for: 3 new posts in Open discussion' })).toBeInTheDocument();

      const lessons = lineOf(/2 new lessons in/);
      expect(within(lessons).queryByText(/Lesson two/)).toBeNull();
      expect(within(lessons).queryByText(/Excerpt of two/)).toBeNull();

      // A line of one item is unchanged
      const one = lineOf(/Single post/);
      expect(within(one).getByText(/Excerpt of single/)).toBeInTheDocument();
      expect(within(one).getByRole('button', { name: /^More for: .*Single post/ })).toBeInTheDocument();
      // A thread with several new replies is unchanged
      expect(within(lineOf(/3 new replies in Thread title/)).getByText(/Excerpt of thread/)).toBeInTheDocument();
    });

    it('marks a line that was not seen with the word "New" and bold text, and shows the space and the time', async () => {
      api.following.mockResolvedValue(
        page([makeFollowing('a', { title: 'Fresh' }), makeFollowing('b', { title: 'Old news', seen_at: new Date() })]),
      );
      renderBell();
      await openPanel();

      const fresh = lineOf(/Fresh/);
      expect(within(fresh).getByText('New')).toBeInTheDocument();
      expect(within(fresh).getByRole('link')).toHaveClass('font-bold');
      expect(within(fresh).getByText('Open discussion')).toBeInTheDocument();
      expect(within(lineOf(/Old news/)).queryByText('New')).toBeNull();
    });

    it('does not count its lines in the bell and does not announce them', async () => {
      api.following.mockResolvedValue(page([makeFollowing('a'), makeFollowing('b')]));
      renderBell();
      await openPanel();
      await within(followingSection()).findAllByRole('listitem');

      expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument();
      expect(api.count).toHaveBeenCalledTimes(1);
      // The status text of the panel stays empty: nothing of "Following" is announced
      expect(within(panel()).getAllByRole('status')[0]).toHaveTextContent('');
    });

    it('shows an error with "Retry Following" that loads again, without touching "Needs you"', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      api.following.mockResolvedValueOnce(failure());
      renderBell();
      const user = await openPanel();

      expect(await within(panel()).findByText('We could not load Following.')).toBeInTheDocument();
      expect(within(panel()).getByText('Needs you title')).toBeInTheDocument();

      api.following.mockResolvedValue(page([makeFollowing('a', { title: 'Back again' })]));
      await user.click(within(panel()).getByRole('button', { name: 'Retry Following' }));
      expect(await within(followingSection()).findByText(/Back again/)).toBeInTheDocument();
    });

    it('opens a line like a "Needs you" line: marks the row read and closes the panel', async () => {
      api.following.mockResolvedValue(page([makeFollowing('a', { title: 'Open me' })]));
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/Open me/);

      await user.click(within(lineOf(/Open me/)).getByRole('link'));

      expect(api.read).toHaveBeenCalledWith('a');
      expect(screen.queryByRole('region', { name: 'Notifications' })).toBeNull();
    });

    it('marks the lines seen after one second, like the others', async () => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
      api.following.mockResolvedValue(page([makeFollowing('a')]));
      renderBell();
      await openPanel(userEvent.setup({ advanceTimers: vi.advanceTimersByTime }));
      await within(followingSection()).findAllByRole('listitem');

      await act(async () => {
        await vi.advanceTimersByTimeAsync(1100);
      });

      expect(api.seen).toHaveBeenCalledWith(['a']);
    });
  });

  describe('"I am caught up"', () => {
    beforeEach(() => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      api.following.mockResolvedValue(page([makeFollowing('a', { title: 'One' }), makeFollowing('b', { title: 'Two' })]));
    });

    it('is at the top of the heading, clears every "Following" line with one request, and never touches "Needs you"', async () => {
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/One/);

      await user.click(within(followingSection()).getByRole('button', { name: 'I am caught up' }));

      expect(api.caughtUp).toHaveBeenCalledOnce();
      expect(api.read).not.toHaveBeenCalled();
      await waitFor(() => expect(within(followingSection()).queryByRole('list')).toBeNull());
      expect(within(panel()).getByText('Needs you title')).toBeInTheDocument();
      expect(within(panel()).queryByRole('button', { name: 'I am caught up' })).toBeNull();
    });

    it('puts the focus on the heading "Following" and says "You are caught up" in the status text', async () => {
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/One/);

      await user.click(within(followingSection()).getByRole('button', { name: 'I am caught up' }));

      await waitFor(() => expect(within(panel()).getByRole('heading', { name: 'Following' })).toHaveFocus());
      const status = within(panel()).getAllByRole('status')[0];
      expect(status).toHaveTextContent('You are caught up');
      expect(status).toHaveAttribute('aria-live', 'polite');
    });

    it('says so with role alert when the server refuses, and keeps the lines', async () => {
      api.caughtUp.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/One/);

      await user.click(within(followingSection()).getByRole('button', { name: 'I am caught up' }));

      expect(await within(panel()).findByRole('alert')).toHaveTextContent('We could not clear Following. Try again.');
      expect(within(followingSection()).getAllByRole('listitem')).toHaveLength(2);
    });

    it('never uses disabled: a second press while the first waits is ignored, with aria-busy', async () => {
      let resolve: (value: { ok: true; message: string; data: { marked: number } }) => void = () => undefined;
      api.caughtUp.mockReturnValue(new Promise((done) => (resolve = done)));
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/One/);
      const button = within(followingSection()).getByRole('button', { name: 'I am caught up' });

      await user.click(button);
      await user.click(button);

      expect(button).not.toBeDisabled();
      expect(button).toHaveAttribute('aria-busy', 'true');
      expect(button).toHaveAttribute('aria-disabled', 'true');
      expect(api.caughtUp).toHaveBeenCalledOnce();
      await act(async () => resolve({ ok: true, message: '', data: { marked: 2 } }));
    });
  });

  describe('"Stop following"', () => {
    const threadLines = () =>
      page([
        makeFollowing('a', { title: 'First thread' }),
        makeFollowing('b', { title: 'Second thread' }),
        makeFollowing('chat', {
          kind: 'thread_reply',
          title: null,
          target: {
            post_slug: null,
            post_cuid: null,
            comment_cuid: null,
            chat_message_cuid: 'reply-1',
            thread_cuid: 'root-1',
            resource_cuid: null,
            resource_slug: null,
            page_slug: null,
          },
        }),
      ]);

    it('names the button after the line, stops the thread, reads the row and removes the line', async () => {
      api.following.mockResolvedValue(threadLines());
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/First thread/);

      const button = within(lineOf(/First thread/)).getByRole('button', { name: /^Stop following: 3 new replies in First thread/ });
      await user.click(button);

      expect(follow.stopPost).toHaveBeenCalledWith('c1', 's1', 'post-cuid-a');
      expect(api.read).toHaveBeenCalledWith('a');
      await waitFor(() => expect(within(followingSection()).queryByText(/First thread/)).toBeNull());
      expect(within(panel()).getAllByRole('status')[0]).toHaveTextContent('You stopped following this thread');
    });

    it('stops a chat thread by its first message', async () => {
      api.following.mockResolvedValue(threadLines());
      renderBell();
      const user = await openPanel();
      const line = await within(followingSection()).findByText(/3 new replies in a thread of Open discussion/);

      await user.click(within(line.closest('li') as HTMLElement).getByRole('button', { name: /^Stop following:/ }));

      expect(follow.stopMessage).toHaveBeenCalledWith('c1', 's1', 'root-1');
    });

    it('moves the focus to the next line, else the line before, else the heading "Following"', async () => {
      api.following.mockResolvedValue(
        page([makeFollowing('a', { title: 'First thread' }), makeFollowing('b', { title: 'Second thread' })]),
      );
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/First thread/);

      await user.click(within(lineOf(/First thread/)).getByRole('button', { name: /^Stop following:/ }));
      await waitFor(() => expect(within(lineOf(/Second thread/)).getByRole('link')).toHaveFocus());

      await user.click(within(lineOf(/Second thread/)).getByRole('button', { name: /^Stop following:/ }));
      await waitFor(() => expect(within(panel()).getByRole('heading', { name: 'Following' })).toHaveFocus());
    });

    it('keeps the line and says so when the server refuses', async () => {
      api.following.mockResolvedValue(threadLines());
      follow.stopPost.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/First thread/);

      await user.click(within(lineOf(/First thread/)).getByRole('button', { name: /^Stop following:/ }));

      expect(await within(panel()).findByRole('alert')).toHaveTextContent('We could not stop following. Try again.');
      expect(within(followingSection()).getByText(/First thread/)).toBeInTheDocument();
    });

    it('is on the lines of a thread only: a new post, new messages and a new lesson have none', async () => {
      api.following.mockResolvedValue(
        page([
          makeFollowing('b', { kind: 'space_post', reason: 'space_everything', title: 'A post', items_count: 1 }),
          makeFollowing('d', { kind: 'space_chat', reason: 'space_everything', title: null }),
          makeFollowing('e', { kind: 'lesson_new', reason: 'started_course', title: 'A lesson', items_count: 1 }),
        ]),
      );
      renderBell();
      await openPanel();
      await within(followingSection()).findAllByRole('listitem');

      expect(within(followingSection()).queryByRole('button', { name: /^Stop following/ })).toBeNull();
    });

    it('is not on a "Needs you" line, which has "Done"', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      renderBell();
      await openPanel();
      await screen.findByText('Needs you title');

      const line = within(panel()).getByText('Needs you title').closest('li') as HTMLElement;
      expect(within(line).getByRole('button', { name: /^Done:/ })).toBeInTheDocument();
      expect(within(line).queryByRole('button', { name: /^Stop following/ })).toBeNull();
    });
  });

  describe('the menu of a line', () => {
    beforeEach(() => {
      api.following.mockResolvedValue(
        page([makeFollowing('a', { title: 'First thread' }), makeFollowing('b', { title: 'Second thread' })]),
      );
    });

    async function openMenu(text: RegExp) {
      const user = await openPanel();
      await within(followingSection()).findByText(text);
      await user.click(within(lineOf(text)).getByRole('button', { name: /^More for:/ }));
      return { user, menu: await screen.findByRole('menu') };
    }

    it('holds "Mute this thread", "Mute <space>" and "Why did I get this?"', async () => {
      renderBell();
      const { menu } = await openMenu(/First thread/);

      expect(
        within(menu)
          .getAllByRole('menuitem')
          .map((item) => item.textContent),
      ).toEqual(['Mute this thread', 'Mute Open discussion', 'Why did I get this?']);
    });

    it('offers no "Mute this thread" on a line of a space, which belongs to no thread', async () => {
      api.following.mockResolvedValue(
        page([makeFollowing('d', { kind: 'space_chat', reason: 'space_everything', title: null, items_count: 5 })]),
      );
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/New messages in Open discussion/);
      await user.click(within(lineOf(/New messages/)).getByRole('button', { name: /^More for:/ }));

      const menu = await screen.findByRole('menu');
      expect(within(menu).queryByRole('menuitem', { name: 'Mute this thread' })).toBeNull();
      expect(within(menu).getByRole('menuitem', { name: 'Mute Open discussion' })).toBeInTheDocument();
    });

    it('the menu is also on a "Needs you" line', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      renderBell();
      const user = await openPanel();
      await screen.findByText('Needs you title');
      const line = within(panel()).getByText('Needs you title').closest('li') as HTMLElement;

      await user.click(within(line).getByRole('button', { name: /^More for:/ }));

      expect(await screen.findByRole('menuitem', { name: 'Why did I get this?' })).toBeInTheDocument();
    });

    it('does not close the panel when the menu opens or closes with Escape', async () => {
      renderBell();
      const { user } = await openMenu(/First thread/);

      await user.keyboard('{Escape}');

      await waitFor(() => expect(screen.queryByRole('menu')).toBeNull());
      expect(screen.getByRole('region', { name: 'Notifications' })).toBeInTheDocument();
    });

    it('"Mute this thread" applies at once, removes the line and says "Muted. Undo"', async () => {
      renderBell();
      const { user, menu } = await openMenu(/First thread/);

      await user.click(within(menu).getByRole('menuitem', { name: 'Mute this thread' }));

      expect(follow.mutePost).toHaveBeenCalledWith('c1', 's1', 'post-cuid-a');
      await waitFor(() => expect(within(followingSection()).queryByText(/First thread/)).toBeNull());
      const [status] = within(panel()).getAllByRole('status');
      if (!status) {
        throw new Error('A status message expected');
      }
      expect(status).toHaveTextContent('Muted. Undo');
      expect(within(status).getByRole('button', { name: 'Undo' })).toBeInTheDocument();
      // No question is asked
      expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('"Undo" calls the route that gives back the exact earlier state, and reads no state before the mute', async () => {
      renderBell();
      const { user, menu } = await openMenu(/First thread/);
      await user.click(within(menu).getByRole('menuitem', { name: 'Mute this thread' }));

      await user.click(await screen.findByRole('button', { name: 'Undo' }));

      expect(follow.unmutePost).toHaveBeenCalledWith('c1', 's1', 'post-cuid-a');
      expect(follow.getPost).not.toHaveBeenCalled();
      expect(follow.followPost).not.toHaveBeenCalled();
      expect(follow.stopPost).not.toHaveBeenCalled();
      await waitFor(() => expect(within(panel()).getAllByRole('status')[0]).toHaveTextContent('Mute undone'));
    });

    it('"Muted. Undo" lasts 8 seconds', async () => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
      renderBell();
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      await openPanel(user);
      await within(followingSection()).findByText(/First thread/);
      await user.click(within(lineOf(/First thread/)).getByRole('button', { name: /^More for:/ }));
      await user.click(within(await screen.findByRole('menu')).getByRole('menuitem', { name: 'Mute this thread' }));
      expect(await screen.findByRole('button', { name: 'Undo' })).toBeInTheDocument();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(UNDO_MS - 500);
      });
      expect(screen.getByRole('button', { name: 'Undo' })).toBeInTheDocument();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(700);
      });
      expect(screen.queryByRole('button', { name: 'Undo' })).toBeNull();
    });

    it('"Mute <space>" sets the level to Muted, removes every line of the space and can be undone', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      follow.getLevel.mockResolvedValue(follow.level('mine', false));
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/First thread/);
      await user.click(within(lineOf(/First thread/)).getByRole('button', { name: /^More for:/ }));

      await user.click(within(await screen.findByRole('menu')).getByRole('menuitem', { name: 'Mute Open discussion' }));

      expect(follow.setLevel).toHaveBeenCalledWith('c1', 's1', 'none');
      await waitFor(() => expect(within(followingSection()).queryByRole('listitem')).toBeNull());
      expect(within(panel()).queryByText('Needs you title')).toBeNull();
      expect(within(panel()).getAllByRole('status')[0]).toHaveTextContent('Muted. Undo');

      // The member had no choice: Undo forgets the mute
      await user.click(screen.getByRole('button', { name: 'Undo' }));
      expect(follow.resetLevel).toHaveBeenCalledWith('c1', 's1');
    });

    it('says so, and keeps the lines, when the mute fails', async () => {
      follow.setLevel.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/First thread/);
      await user.click(within(lineOf(/First thread/)).getByRole('button', { name: /^More for:/ }));
      await user.click(within(await screen.findByRole('menu')).getByRole('menuitem', { name: 'Mute Open discussion' }));

      expect(await within(panel()).findByRole('alert')).toHaveTextContent('We could not mute Open discussion. Try again.');
      expect(within(followingSection()).getAllByRole('listitem')).toHaveLength(2);
    });

    it('"Why did I get this?" shows the reason as a sentence and a link named after its setting', async () => {
      api.following.mockResolvedValue(page([makeFollowing('a', { title: 'First thread', follow_reason: 'replied' })]));
      renderBell();
      const { user, menu } = await openMenu(/First thread/);

      await user.click(within(menu).getByRole('menuitem', { name: 'Why did I get this?' }));

      const line = lineOf(/First thread/);
      expect(within(line).getByText('You follow this thread because you replied in it.')).toBeInTheDocument();
      const link = within(line).getByRole('link', { name: 'Notification settings for Open discussion' });
      expect(link).toHaveAttribute('href', '/profile.notifications/#notifications-space-s1');
    });

    it('says why for each reason', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title', reason: 'mentioned' })]));
      api.following.mockResolvedValue(
        page([makeFollowing('e', { kind: 'space_post', reason: 'space_everything', title: 'A post', items_count: 1 })]),
      );
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/A post/);

      await user.click(within(lineOf(/A post/)).getByRole('button', { name: /^More for:/ }));
      await user.click(await screen.findByRole('menuitem', { name: 'Why did I get this?' }));
      expect(within(lineOf(/A post/)).getByText(/Your level for Open discussion is Everything/)).toBeInTheDocument();

      const need = within(panel()).getByText('Needs you title').closest('li') as HTMLElement;
      await user.click(within(need).getByRole('button', { name: /^More for:/ }));
      await user.click(await screen.findByRole('menuitem', { name: 'Why did I get this?' }));
      expect(within(need).getByText('Someone mentioned you.')).toBeInTheDocument();
    });
  });

  describe('accessibility', () => {
    it('has no automated violation (axe) with both headings, a menu open and the status text', async () => {
      api.list.mockResolvedValue(page([makeNotification('need', { title: 'Needs you title' })]));
      api.following.mockResolvedValue(
        page([makeFollowing('a', { title: 'First thread' }), makeFollowing('b', { title: 'Second thread' })]),
      );
      renderBell();
      const user = await openPanel();
      await within(followingSection()).findByText(/First thread/);
      await user.click(within(lineOf(/First thread/)).getByRole('button', { name: /^More for:/ }));
      await user.click(await screen.findByRole('menuitem', { name: 'Why did I get this?' }));
      await user.click(within(followingSection()).getByRole('button', { name: 'I am caught up' }));
      await waitFor(() => expect(within(panel()).getAllByRole('status')[0]).toHaveTextContent('You are caught up'));

      const results = await axe.run(panel(), { rules: { 'color-contrast': { enabled: false } } });

      expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.html).join(' | ')}`)).toEqual([]);
    });

    it('has no automated violation (axe) with the lines of "Following" and the menu button names', async () => {
      api.following.mockResolvedValue(
        page([makeFollowing('a', { title: 'First thread' }), makeFollowing('b', { title: 'Second thread' })]),
      );
      renderBell();
      await openPanel();
      await within(followingSection()).findByText(/First thread/);

      const results = await axe.run(panel(), { rules: { 'color-contrast': { enabled: false } } });

      expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.html).join(' | ')}`)).toEqual([]);
    });
  });
});
