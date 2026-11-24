import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { Comment, Community, Pagination, Post, PostPermissions, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import PostPage from './Show';

// The comments of the page fit in one page (ADR 0073)
const ONE_PAGE = (comments: Comment[]): Pagination => ({
  limit: 100,
  offset: 0,
  previousOffset: null,
  nextOffset: null,
  currentPage: 1,
  pageCount: 1,
  totalCount: comments.reduce((count, comment) => count + 1 + (comment.replies?.length ?? 0), 0),
});

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { reload: vi.fn(), visit: vi.fn(), replace: vi.fn() },
  usePage: () => ({
    props: { auth: { user: { cuid: 'ada' } }, jetstream: { flash: {} }, newsletter: { enabled: false, turnstileSiteKey: null } },
    url: '/',
  }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));
vi.mock('@/Components/Posts/PostComposer', () => ({ PostComposer: () => null }));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

// jsdom has no scrollIntoView: the focus after a delete scrolls the title into view
beforeEach(() => {
  Element.prototype.scrollIntoView = vi.fn();
});

const now = new Date();
const ada = { cuid: 'ada', name: 'Ada', username: 'ada' };
const bob = { cuid: 'bob', name: 'Bob', username: 'bob' };

function comment(cuid: string, author: typeof ada, replies: Comment[] = [], parent: string | null = null): Comment {
  return {
    cuid,
    body: cuid,
    body_html: `<p>${cuid}</p>`,
    edited_at: null,
    deleted_at: null,
    author,
    parent_cuid: parent,
    replies,
    created_at: now,
    updated_at: now,
  };
}

const post: Post = {
  cuid: 'p1',
  title: 'My plan',
  slug: 'my-plan',
  body_html: '<p>The plan</p>',
  is_pinned: false,
  pinned_at: null,
  edited_at: null,
  deleted_at: null,
  author: ada,
  space_cuid: 's1',
  created_at: now,
  updated_at: now,
};

const author: PostPermissions = {
  canManageSpaces: false,
  canContribute: true,
  canModerate: false,
  canUpdatePost: true,
  canDeletePost: true,
  canDeletePostWithComments: false,
  canPinPost: false,
  canComment: true,
};

const moderator: PostPermissions = { ...author, canModerate: true, canDeletePostWithComments: true, canUpdatePost: false };

function renderPage(
  comments: Comment[],
  permissions: PostPermissions = author,
  shown: Post = post,
  pagination: Pagination = ONE_PAGE(comments),
) {
  render(
    <AppDataProvider>
      <PostPage
        community={{ cuid: 'c1', slug: 'tests', name: 'Tests', visibility: 'private' } as Community}
        space={{ cuid: 's1', slug: 'talk', name: 'Talk', type: 'discussion', emoji: '💬' } as Space}
        spaceGroups={[]}
        post={shown}
        comments={comments}
        commentsPagination={pagination}
        permissions={permissions}
      />
    </AppDataProvider>,
  );
}

async function openDeleteDialog() {
  await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
  return screen.findByRole('dialog', { name: 'Delete this post?' });
}

describe('PostPage, the delete that keeps the comments of other people (#1565)', () => {
  afterEach(() => {
    vi.clearAllMocks();
    vi.restoreAllMocks();
  });

  it('says that the comments of other people stay and that the own post becomes a marker', async () => {
    renderPage([comment('c1', bob)]);

    const dialog = await openDeleteDialog();

    expect(dialog).toHaveAccessibleDescription(
      'The comments of other people stay. Your post is replaced by a marker. This cannot be undone.',
    );
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('says that the own comments go too', async () => {
    renderPage([comment('c1', ada)]);
    expect(await openDeleteDialog()).toHaveAccessibleDescription('Your comments under it are deleted too. This cannot be undone.');
  });

  it('says nothing more for a post without comments', async () => {
    renderPage([]);
    expect(await openDeleteDialog()).toHaveAccessibleDescription('This cannot be undone.');
  });

  it('offers a moderator to delete the comments too, with their number, and the text follows the box', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.posts, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    renderPage([comment('c1', bob, [comment('r1', ada, [], 'c1')])], moderator, { ...post, author: bob });

    const dialog = await openDeleteDialog();
    const box = within(dialog).getByRole('checkbox', { name: 'Also delete the comments (2)' });
    expect(box).not.toBeChecked();
    expect(dialog).toHaveAccessibleDescription(
      'The comments of other people stay. The post is replaced by a marker. This cannot be undone.',
    );

    await userEvent.click(box);
    expect(dialog).toHaveAccessibleDescription('Its comments are deleted too. This cannot be undone.');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith('c1', 's1', 'p1', { delete_comments: true });
    await waitFor(() => expect(router.visit).toHaveBeenCalledWith('/spaces.show'));
  });

  it('counts every comment of the post while some are not loaded, and does not say that the post goes (ADR 0073)', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.posts, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    // The post is gone: it had no comment of other people
    const get = vi
      .spyOn(knowiiApiClient.communities.posts, 'get')
      .mockResolvedValue({ ok: false, status: 404, message: 'Not found', errors: {} });
    renderPage([comment('c1', ada)], moderator, post, { ...ONE_PAGE([]), nextOffset: 100, pageCount: 2, totalCount: 150 });

    const dialog = await openDeleteDialog();
    expect(within(dialog).getByRole('checkbox', { name: 'Also delete the comments (150)' })).not.toBeChecked();
    expect(dialog).toHaveAccessibleDescription(
      'Your comments under it are deleted too. If it has comments of other people, they stay and the post is replaced by a marker. This cannot be undone.',
    );

    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));
    expect(remove).toHaveBeenCalledWith('c1', 's1', 'p1', {});
    // The page asks the server whether a marker stayed
    await waitFor(() => expect(get).toHaveBeenCalled());
    await waitFor(() => expect(router.visit).toHaveBeenCalledWith('/spaces.show'));
  });

  it('shows no box to a moderator for a post without comments', async () => {
    renderPage([], moderator);
    expect(within(await openDeleteDialog()).queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('keeps the author on the page of the marker, with the discussion, and puts the focus on its title', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.posts, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    const marker: Post = { ...post, title: '', slug: 'deleted-p1', body_html: '', author: null, deleted_at: now };
    vi.spyOn(knowiiApiClient.communities.posts, 'get').mockResolvedValue({ ok: true, message: '', data: marker });
    renderPage([comment('c1', bob)]);

    await userEvent.click(within(await openDeleteDialog()).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith('c1', 's1', 'p1', {});
    const title = await screen.findByRole('heading', { level: 1, name: 'This post was deleted' });
    expect(screen.queryByText('The plan')).not.toBeInTheDocument();
    expect(screen.getByText('c1', { selector: 'p' })).toBeInTheDocument();
    expect(router.visit).not.toHaveBeenCalled();
    // The address follows the new slug, without a request
    expect(router.replace).toHaveBeenCalledWith(expect.objectContaining({ url: '/posts.show', preserveState: true }));
    await waitFor(() => expect(title).toHaveFocus());
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    // Nobody edits, comments or deletes the marker again
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();
    expect(screen.queryByRole('form', { name: 'Write a comment' })).not.toBeInTheDocument();
  });

  it('goes back to the space after a delete that removes the post, as before', async () => {
    vi.spyOn(knowiiApiClient.communities.posts, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    const get = vi.spyOn(knowiiApiClient.communities.posts, 'get');
    renderPage([comment('c1', ada)]);

    await userEvent.click(within(await openDeleteDialog()).getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(router.visit).toHaveBeenCalledWith('/spaces.show'));
    expect(get).not.toHaveBeenCalled();
  });

  it('lets a moderator delete a marker, always with its comments', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.posts, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    const marker: Post = { ...post, title: '', slug: 'deleted-p1', body_html: '', author: null, deleted_at: now };
    renderPage([comment('c1', bob)], { ...moderator, canDeletePost: false }, marker);

    const dialog = await openDeleteDialog();
    expect(dialog).toHaveAccessibleDescription('Its comments are deleted too. This cannot be undone.');
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith('c1', 's1', 'p1', { delete_comments: true });
  });
});
