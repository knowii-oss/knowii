import { describe, expect, it, vi, beforeAll } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, Post, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { PostList } from './PostList';
import { emitSocketEvent } from '@/test-utils/fake-echo';

vi.mock('@inertiajs/react', () => ({
  Link: ({
    children,
    href,
    preserveState: _preserveState,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveState?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  // Ziggy's route() helper is a global in the app
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: Record<string, string>) =>
      `/community/${params['communitySlug']}/space/${params['spaceSlug']}/post/${params['postSlug']}`,
    writable: true,
  });
});

const now = new Date();
const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'public',
  created_at: now,
  updated_at: now,
} as Community;
const space = { cuid: 's1', name: 'General', slug: 'general', type: 'discussion' } as Space;

function post(cuid: string, title: string, pinned = false): Post {
  return {
    cuid,
    title,
    slug: title.toLowerCase(),
    body_html: `<p>${title} body</p>`,
    is_pinned: pinned,
    pinned_at: pinned ? now : null,
    edited_at: null,
    author: { cuid: 'u1', name: 'Ada', username: 'ada' },
    space_cuid: 's1',
    created_at: now,
    updated_at: now,
  };
}

const pagination = (nextOffset: number | null, totalCount: number) => ({
  limit: 20,
  offset: 0,
  previousOffset: null,
  nextOffset,
  currentPage: 1,
  pageCount: 2,
  totalCount,
});

function renderList(canContribute = true) {
  render(
    <AppDataProvider>
      <PostList
        community={community}
        space={space}
        initialPosts={{ items: [post('p1', 'Pinned', true), post('p2', 'Second')], pagination: pagination(20, 3) }}
        canContribute={canContribute}
      />
    </AppDataProvider>,
  );
}

describe('PostList', () => {
  it('shows the posts as articles with links, the pinned one marked', () => {
    renderList();
    const articles = screen.getAllByRole('article');
    expect(articles).toHaveLength(2);
    expect(within(articles[0] as HTMLElement).getByText('Pinned', { selector: 'p' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Second' })).toHaveAttribute('href', '/community/tests/space/general/post/second');
  });

  it('loads the next page without duplicates', async () => {
    const list = vi.spyOn(knowiiApiClient.communities.posts, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: { items: [post('p2', 'Second'), post('p3', 'Third')], pagination: pagination(null, 3) },
    });
    renderList();

    await userEvent.click(screen.getByRole('button', { name: 'Load more posts' }));

    expect(list).toHaveBeenCalledWith('c1', 's1', 20, 20);
    expect(screen.getAllByRole('article')).toHaveLength(3);
    expect(screen.queryByRole('button', { name: 'Load more posts' })).not.toBeInTheDocument();
  });

  it('offers the composer only to members who can post', async () => {
    renderList(false);
    expect(screen.queryByRole('button', { name: 'New post' })).not.toBeInTheDocument();
  });

  it('opens the composer with the title field focused', async () => {
    renderList();
    await userEvent.click(screen.getByRole('button', { name: 'New post' }));
    const dialog = await screen.findByRole('dialog', { name: 'New post in General' });
    expect(within(dialog).getByRole('textbox', { name: 'Title' })).toHaveFocus();
    expect(within(dialog).getByRole('button', { name: 'Publish' })).toBeDisabled();
  });
});

describe('PostList live updates (#1180)', () => {
  const channel = 'community.c1.space.s1';

  function renderLive(currentUserCuid: string | null = 'me') {
    render(
      <AppDataProvider>
        <PostList
          community={community}
          space={space}
          initialPosts={{ items: [post('p1', 'Pinned', true), post('p2', 'Second')], pagination: pagination(null, 2) }}
          canContribute
          currentUserCuid={currentUserCuid}
        />
      </AppDataProvider>,
    );
  }

  it('shows a "new posts" button instead of moving the list, and loads them on demand', async () => {
    const list = vi.spyOn(knowiiApiClient.communities.posts, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: { items: [post('p1', 'Pinned', true), post('p9', 'Fresh'), post('p2', 'Second')], pagination: pagination(null, 3) },
    });
    renderLive();

    act(() => emitSocketEvent(channel, 'space.post.created', { space_cuid: 's1', post_cuid: 'p9', author_cuid: 'someone' }));
    act(() => emitSocketEvent(channel, 'space.post.created', { space_cuid: 's1', post_cuid: 'p9', author_cuid: 'someone' }));

    expect(screen.getAllByRole('article')).toHaveLength(2);
    await userEvent.click(screen.getByRole('button', { name: '1 new post' }));

    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(3));
    expect(list).toHaveBeenCalledWith('c1', 's1', expect.any(Number), 0);
    expect(screen.queryByRole('button', { name: /new post$/ })).not.toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('link', { name: 'Pinned' })).toHaveFocus());
  });

  it('shows no button for the posts of the member, who already sees them', () => {
    renderLive('me');

    act(() => emitSocketEvent(channel, 'space.post.created', { space_cuid: 's1', post_cuid: 'p9', author_cuid: 'me' }));

    expect(screen.queryByRole('button', { name: '1 new post' })).not.toBeInTheDocument();
  });

  it('removes a deleted post and loads an edited one again', async () => {
    const get = vi
      .spyOn(knowiiApiClient.communities.posts, 'get')
      .mockResolvedValue({ ok: true, message: '', data: { ...post('p2', 'Second'), title: 'Second, edited' } });
    renderLive();

    act(() => emitSocketEvent(channel, 'space.post.updated', { space_cuid: 's1', post_cuid: 'p2' }));
    expect(await screen.findByRole('link', { name: 'Second, edited' })).toBeInTheDocument();
    expect(get).toHaveBeenCalledWith('c1', 's1', 'p2');

    act(() => emitSocketEvent(channel, 'space.post.deleted', { space_cuid: 's1', post_cuid: 'p1' }));
    expect(screen.getAllByRole('article')).toHaveLength(1);
  });

  it('ignores posts that are not shown and reactions of comments', () => {
    const get = vi.spyOn(knowiiApiClient.communities.posts, 'get');
    renderLive();

    act(() => emitSocketEvent(channel, 'space.post.updated', { space_cuid: 's1', post_cuid: 'elsewhere' }));
    act(() => emitSocketEvent(channel, 'space.reactions.changed', { space_cuid: 's1', post_cuid: 'p2', comment_cuid: 'c1' }));

    expect(get).not.toHaveBeenCalled();
  });
});
