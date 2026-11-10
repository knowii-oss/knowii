import { describe, expect, it, vi, afterEach, beforeEach } from 'vitest';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Comment } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { CommentSection } from './CommentSection';
import { emitSocketEvent } from '@/test-utils/fake-echo';

const now = new Date();
const ada = { cuid: 'ada', name: 'Ada', username: 'ada' };
const bob = { cuid: 'bob', name: 'Bob', username: 'bob' };

function comment(cuid: string, author: typeof ada, body: string, parent: string | null = null, replies: Comment[] = []): Comment {
  return {
    cuid,
    body,
    body_html: `<p>${body}</p>`,
    edited_at: null,
    author,
    parent_cuid: parent,
    replies,
    created_at: now,
    updated_at: now,
  };
}

const initial = [comment('c1', ada, 'First', null, [comment('r1', bob, 'A reply', 'c1')]), comment('c2', bob, 'Second')];

function renderSection(overrides: Partial<Parameters<typeof CommentSection>[0]> = {}) {
  const onCountChange = vi.fn();
  render(
    <AppDataProvider>
      <CommentSection
        subject={{ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }}
        initialComments={initial}
        currentUserCuid="ada"
        canComment={true}
        canModerate={false}
        onCountChange={onCountChange}
        {...overrides}
      />
    </AppDataProvider>,
  );
  return onCountChange;
}

describe('CommentSection', () => {
  afterEach(() => vi.restoreAllMocks());

  it('shows the comments with their replies and the total count', () => {
    renderSection();
    expect(screen.getByRole('heading', { name: '3 comments' })).toBeInTheDocument();
    const replies = screen.getByRole('list', { name: 'Replies to the comment by Ada' });
    expect(within(replies).getByText('A reply')).toBeInTheDocument();
  });

  it('lets authors edit and delete their own comments only, unless they moderate', () => {
    renderSection();
    expect(screen.getAllByRole('button', { name: 'Edit your comment' })).toHaveLength(1);
    expect(screen.getAllByRole('button', { name: /^Delete the comment by/ })).toHaveLength(1);
  });

  it('lets moderators delete every comment', () => {
    renderSection({ canModerate: true });
    expect(screen.getAllByRole('button', { name: /^Delete the comment by/ })).toHaveLength(3);
  });

  it('replies to a reply in the thread of its top-level comment', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.comments, 'create')
      .mockResolvedValue({ ok: true, message: '', data: comment('r2', ada, 'Me too', 'c1') });
    const onCountChange = renderSection();

    const thread = screen.getByRole('list', { name: 'Replies to the comment by Ada' });
    await userEvent.click(within(thread).getByRole('button', { name: 'Reply to Bob' }));
    const editor = screen.getByRole('combobox', { name: 'Reply to Ada' });
    expect(editor).toHaveFocus();
    await userEvent.type(editor, 'Me too');
    await userEvent.click(screen.getByRole('button', { name: 'Reply' }));

    expect(create).toHaveBeenCalledWith(
      { communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' },
      { body: 'Me too', parent_cuid: 'c1' },
    );
    expect(await screen.findByText('Me too', { selector: 'p' })).toBeInTheDocument();
    expect(onCountChange).toHaveBeenLastCalledWith(4);
  });

  it('asks before it deletes a comment and its replies', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.comments, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    const onCountChange = renderSection();

    await userEvent.click(screen.getByRole('button', { name: 'Delete the comment by Ada' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete this comment?' });
    expect(dialog).toHaveAccessibleDescription('Its replies are deleted too. This cannot be undone.');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 'c1');
    expect(onCountChange).toHaveBeenLastCalledWith(1);
    expect(screen.getByRole('heading', { name: '1 comment' })).toBeInTheDocument();
  });

  it('hides the comment form from people who cannot comment', () => {
    renderSection({ canComment: false });
    expect(screen.queryByRole('form', { name: 'Write a comment' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Reply/ })).not.toBeInTheDocument();
  });

  it('comments a resource through the resource endpoints (#1034)', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.comments, 'create')
      .mockResolvedValue({ ok: true, message: '', data: comment('n1', ada, 'Useful') });
    renderSection({ subject: { communityCuid: 'community', spaceCuid: 'space', resourceCuid: 'resource' } });

    await userEvent.type(screen.getByRole('combobox', { name: 'Write a comment' }), 'Useful');
    await userEvent.click(screen.getByRole('button', { name: 'Comment' }));

    expect(create).toHaveBeenCalledWith(
      { communityCuid: 'community', spaceCuid: 'space', resourceCuid: 'resource' },
      { body: 'Useful', parent_cuid: null },
    );
  });
});

describe('CommentSection live updates (#1180)', () => {
  const channel = 'community.community.space.space';

  it('loads the comments again when one of this post changes, and keeps the reply being typed', async () => {
    const list = vi
      .spyOn(knowiiApiClient.communities.comments, 'list')
      .mockResolvedValue({ ok: true, message: '', data: [...initial, comment('c3', bob, 'Live one')] });
    const onCountChange = renderSection();

    await userEvent.click(screen.getByRole('button', { name: 'Reply to Ada' }));
    const reply = screen.getByRole('combobox', { name: 'Reply to Ada' });
    await userEvent.type(reply, 'Half-written');

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'post', comment_cuid: 'c3' }));

    expect(await screen.findByText('Live one')).toBeInTheDocument();
    expect(list).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' });
    expect(onCountChange).toHaveBeenLastCalledWith(4);
    expect(screen.getByRole('combobox', { name: 'Reply to Ada' })).toHaveValue('Half-written');
  });

  it('ignores the comments of other posts and the reactions of the post itself', () => {
    const list = vi.spyOn(knowiiApiClient.communities.comments, 'list');
    renderSection();

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'other', comment_cuid: 'x' }));
    act(() => emitSocketEvent(channel, 'space.reactions.changed', { space_cuid: 'space', post_cuid: 'post' }));

    expect(list).not.toHaveBeenCalled();
  });
});

describe('CommentSection permalinks and collapsed threads (#1205)', () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.history.replaceState(null, '', '/community/c/space/s/post/p');
    Element.prototype.scrollIntoView = vi.fn();
  });
  afterEach(() => vi.restoreAllMocks());

  it('copies the link to a reply', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    renderSection();

    const thread = screen.getByRole('list', { name: 'Replies to the comment by Ada' });
    await userEvent.click(within(thread).getByRole('button', { name: 'Copy the link to the comment by Bob' }));

    expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/community/c/space/s/post/p#comment-r1`);
    expect(document.getElementById('comment-r1')).toHaveAccessibleName('Comment by Bob');
  });

  it('collapses and expands a thread with the keyboard, and keeps it collapsed for the post', async () => {
    const { unmount } = renderSectionWithUnmount();
    const toggle = screen.getByRole('button', { name: 'Collapse the thread of Ada' });
    expect(toggle).toHaveAttribute('aria-expanded', 'true');

    toggle.focus();
    await userEvent.keyboard('{Enter}');

    expect(screen.queryByText('A reply')).not.toBeInTheDocument();
    expect(screen.getByText('· 1 reply', { exact: false })).toBeInTheDocument();
    const expand = screen.getByRole('button', { name: 'Expand the thread of Ada' });
    expect(expand).toHaveAttribute('aria-expanded', 'false');
    expect(expand).toHaveFocus();
    expect(JSON.parse(window.localStorage.getItem('knowii:collapsed-comments:post') ?? '[]')).toEqual(['c1']);

    unmount();
    renderSection();
    expect(screen.queryByText('A reply')).not.toBeInTheDocument();

    screen.getByRole('button', { name: 'Expand the thread of Ada' }).focus();
    await userEvent.keyboard(' ');
    expect(screen.getByText('A reply')).toBeInTheDocument();
  });

  it('collapses and expands every thread at once', async () => {
    renderSection();

    await userEvent.click(screen.getByRole('button', { name: 'Collapse all' }));
    expect(screen.queryByText('First')).not.toBeInTheDocument();
    expect(screen.queryByText('Second')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Collapse all' })).toBeDisabled();

    await userEvent.click(screen.getByRole('button', { name: 'Expand all' }));
    expect(screen.getByText('First')).toBeInTheDocument();
    expect(screen.getByText('A reply')).toBeInTheDocument();
  });

  it('opens a link to a reply in a collapsed thread: the thread expands and the reply gets the focus', () => {
    window.localStorage.setItem('knowii:collapsed-comments:post', JSON.stringify(['c1', 'c2']));
    window.history.replaceState(null, '', '/community/c/space/s/post/p#comment-r1');

    renderSection();

    const reply = document.getElementById('comment-r1');
    expect(reply).toHaveFocus();
    expect(reply).toHaveAttribute('data-highlighted', 'true');
    expect(Element.prototype.scrollIntoView).toHaveBeenCalled();
    expect(screen.queryByText('Second')).not.toBeInTheDocument();
  });

  it('follows a link on the same page to a collapsed thread', async () => {
    renderSection();
    await userEvent.click(screen.getByRole('button', { name: 'Collapse the thread of Ada' }));

    act(() => {
      window.history.replaceState(null, '', '#comment-r1');
      window.dispatchEvent(new HashChangeEvent('hashchange'));
    });

    expect(screen.getByText('A reply')).toBeInTheDocument();
    expect(document.getElementById('comment-r1')).toHaveFocus();
  });
});

function renderSectionWithUnmount() {
  return render(
    <AppDataProvider>
      <CommentSection
        subject={{ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }}
        initialComments={initial}
        currentUserCuid="ada"
        canComment={true}
        canModerate={false}
      />
    </AppDataProvider>,
  );
}
