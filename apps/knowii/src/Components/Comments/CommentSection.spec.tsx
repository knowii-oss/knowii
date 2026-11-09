import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Comment } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { CommentSection } from './CommentSection';

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
        communityCuid="community"
        spaceCuid="space"
        postCuid="post"
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
    const editor = screen.getByRole('textbox', { name: 'Reply to Ada' });
    expect(editor).toHaveFocus();
    await userEvent.type(editor, 'Me too');
    await userEvent.click(screen.getByRole('button', { name: 'Reply' }));

    expect(create).toHaveBeenCalledWith('community', 'space', 'post', { body: 'Me too', parent_cuid: 'c1' });
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

    expect(remove).toHaveBeenCalledWith('community', 'space', 'post', 'c1');
    expect(onCountChange).toHaveBeenLastCalledWith(1);
    expect(screen.getByRole('heading', { name: '1 comment' })).toBeInTheDocument();
  });

  it('hides the comment form from people who cannot comment', () => {
    renderSection({ canComment: false });
    expect(screen.queryByRole('form', { name: 'Write a comment' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Reply/ })).not.toBeInTheDocument();
  });
});
