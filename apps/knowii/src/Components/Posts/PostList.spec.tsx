import { describe, expect, it, vi, beforeAll } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, Post, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { PostList } from './PostList';

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
