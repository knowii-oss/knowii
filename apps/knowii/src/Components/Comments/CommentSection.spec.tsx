import { describe, expect, it, vi, afterEach, beforeEach } from 'vitest';
import axe from 'axe-core';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Comment, Paginated } from '@knowii/common';
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
    deleted_at: null,
    author,
    parent_cuid: parent,
    replies,
    created_at: now,
    updated_at: now,
  };
}

/**
 * One page of comments that holds every comment (ADR 0073)
 */
function onePage(items: Comment[], nextOffset: number | null = null, totalCount?: number): Paginated<Comment> {
  const total = totalCount ?? items.reduce((count, item) => count + 1 + (item.replies?.length ?? 0), 0);
  return {
    items,
    pagination: {
      limit: 100,
      offset: 0,
      previousOffset: null,
      nextOffset,
      currentPage: 1,
      pageCount: Math.max(1, Math.ceil(total / 100)),
      totalCount: total,
    },
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

// jsdom has no scrollIntoView: the saved comment and the permalinks scroll into view (#1205, #1624)
beforeEach(() => {
  Element.prototype.scrollIntoView = vi.fn();
});

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

    expect(create).toHaveBeenCalledWith(
      { communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' },
      { body: 'Me too', parent_cuid: 'c1' },
    );
    expect(await screen.findByText('Me too', { selector: 'p' })).toBeInTheDocument();
    expect(onCountChange).toHaveBeenLastCalledWith(4);
  });

  it('asks before it deletes a comment, and a comment without replies goes', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.comments, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    const onCountChange = renderSection({ initialComments: [comment('c1', ada, 'First'), comment('c2', bob, 'Second')] });

    await userEvent.click(screen.getByRole('button', { name: 'Delete the comment by Ada' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete this comment?' });
    expect(dialog).toHaveAccessibleDescription('This cannot be undone.');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 'c1', {});
    expect(onCountChange).toHaveBeenLastCalledWith(1);
    expect(screen.getByRole('heading', { name: '1 comment' })).toBeInTheDocument();
  });

  it('shows the marker of a deleted comment with its replies, and nothing of the old comment (#1344)', async () => {
    const marker: Comment = { ...comment('m1', ada, '', null, [comment('r9', bob, 'Still here', 'm1')]), author: null, deleted_at: now };
    renderSection({ initialComments: [marker] });

    expect(screen.getByRole('article', { name: 'Deleted comment' })).toHaveTextContent('This comment was deleted.');
    expect(screen.getByText('Still here')).toBeInTheDocument();
    // No author, no edit, no reactions; the thread stays open to replies
    expect(screen.queryByText('Deleted member')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Edit your comment' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reply in the thread of the deleted comment' })).toBeInTheDocument();
  });

  it('hides the comment form from people who cannot comment', () => {
    renderSection({ canComment: false });
    expect(screen.queryByRole('form', { name: 'Write a comment' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Reply/ })).not.toBeInTheDocument();
  });

  it('puts the focus back in the field after a comment is sent with the keyboard (#1618)', async () => {
    vi.spyOn(knowiiApiClient.communities.comments, 'create').mockResolvedValue({
      ok: true,
      message: '',
      data: comment('c3', ada, 'Hello'),
    });
    renderSection();

    const form = screen.getByRole('form', { name: 'Write a comment' });
    const editor = within(form).getByRole('textbox', { name: 'Write a comment' });
    await userEvent.type(editor, 'Hello');
    const send = within(form).getByRole('button', { name: 'Comment' });
    act(() => send.focus());
    await userEvent.keyboard('{Enter}');

    expect(await screen.findByText('Hello', { selector: 'p' })).toBeInTheDocument();
    expect(editor).toHaveValue('');
    expect(editor).toHaveFocus();
    // The focus left the button before it became disabled
    expect(send).toBeDisabled();
  });

  it('keeps the text and the focus when a comment cannot be sent (#1618)', async () => {
    vi.spyOn(knowiiApiClient.communities.comments, 'create').mockResolvedValue({
      ok: false,
      status: 422,
      message: 'The comment is not valid',
      errors: { body: ['The comment is too long.'] },
    });
    renderSection();

    const form = screen.getByRole('form', { name: 'Write a comment' });
    const editor = within(form).getByRole('textbox', { name: 'Write a comment' });
    await userEvent.type(editor, 'Hello');
    const send = within(form).getByRole('button', { name: 'Comment' });
    act(() => send.focus());
    await userEvent.keyboard('{Enter}');

    expect(await within(form).findByText('The comment is too long.')).toBeInTheDocument();
    expect(editor).toHaveValue('Hello');
    expect(send).toHaveFocus();
    // jsdom does not always drop the focus of a disabled button, Chrome does: the button must not be disabled
    expect(send).not.toHaveAttribute('disabled');
  });

  it('comments a resource through the resource endpoints (#1034)', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.comments, 'create')
      .mockResolvedValue({ ok: true, message: '', data: comment('n1', ada, 'Useful') });
    renderSection({ subject: { communityCuid: 'community', spaceCuid: 'space', resourceCuid: 'resource' } });

    await userEvent.type(screen.getByRole('textbox', { name: 'Write a comment' }), 'Useful');
    await userEvent.click(screen.getByRole('button', { name: 'Comment' }));

    expect(create).toHaveBeenCalledWith(
      { communityCuid: 'community', spaceCuid: 'space', resourceCuid: 'resource' },
      { body: 'Useful', parent_cuid: null },
    );
  });
});

describe('CommentSection on an event (#1516)', () => {
  const channel = 'community.community.space.space';
  const subject = { communityCuid: 'community', spaceCuid: 'space', eventCuid: 'event' };
  afterEach(() => vi.restoreAllMocks());

  it('comments an event and replies through the event endpoints', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.comments, 'create')
      .mockResolvedValueOnce({ ok: true, message: '', data: comment('n1', ada, 'See you there') })
      .mockResolvedValueOnce({ ok: true, message: '', data: comment('r2', ada, 'Me too', 'c1') });
    renderSection({ subject });

    await userEvent.type(screen.getByRole('textbox', { name: 'Write a comment' }), 'See you there');
    await userEvent.click(screen.getByRole('button', { name: 'Comment' }));
    expect(create).toHaveBeenLastCalledWith(subject, { body: 'See you there', parent_cuid: null });

    await userEvent.click(
      within(document.getElementById('comment-c1') as HTMLElement).getAllByRole('button', { name: 'Reply to Ada' })[0] as HTMLElement,
    );
    await userEvent.type(screen.getByRole('textbox', { name: 'Reply to Ada' }), 'Me too');
    await userEvent.click(screen.getByRole('button', { name: 'Reply' }));
    expect(create).toHaveBeenLastCalledWith(subject, { body: 'Me too', parent_cuid: 'c1' });
    expect(await screen.findByText('Me too', { selector: 'p' })).toBeInTheDocument();
  });

  it('loads the comments again on a live update of this event, never on one of another event, post or resource', async () => {
    const list = vi
      .spyOn(knowiiApiClient.communities.comments, 'list')
      .mockResolvedValue({ ok: true, message: '', data: onePage([...initial, comment('c3', bob, 'Live one')]) });
    renderSection({ subject });

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', event_cuid: 'other', comment_cuid: 'x1' }));
    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'event', comment_cuid: 'x2' }));
    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', resource_cuid: 'event', comment_cuid: 'x3' }));
    expect(list).not.toHaveBeenCalled();

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', event_cuid: 'event', comment_cuid: 'c3' }));
    expect(await screen.findByText('Live one')).toBeInTheDocument();
    expect(list).toHaveBeenCalledWith(subject, 0);
  });

  it('has no automated violation (axe)', async () => {
    renderSection({ subject });

    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations).toEqual([]);
  });
});

describe('CommentSection focus after a reply or an edit (#1624)', () => {
  const channel = 'community.community.space.space';
  afterEach(() => vi.restoreAllMocks());

  async function writeReply(opener: HTMLElement, text: string) {
    await userEvent.click(opener);
    const editor = screen.getByRole('textbox', { name: 'Reply to Ada' });
    await userEvent.type(editor, text);
    return editor;
  }

  async function sendWithKeyboard(name: string) {
    const send = screen.getByRole('button', { name });
    act(() => send.focus());
    await userEvent.keyboard('{Enter}');
  }

  it('puts the focus on the new reply once the reply form is gone', async () => {
    vi.spyOn(knowiiApiClient.communities.comments, 'create').mockResolvedValue({
      ok: true,
      message: '',
      data: comment('r2', ada, 'Me too', 'c1'),
    });
    renderSection();

    await writeReply(screen.getByRole('button', { name: 'Reply to Ada' }), 'Me too');
    await sendWithKeyboard('Reply');

    expect(await screen.findByText('Me too', { selector: 'p' })).toBeInTheDocument();
    const saved = document.getElementById('comment-r2');
    await waitFor(() => expect(saved).toHaveFocus());
    expect(saved).toHaveAttribute('tabindex', '-1');
    expect(saved).toHaveTextContent('Me too');
    expect(screen.queryByRole('form', { name: 'Reply to Ada' })).not.toBeInTheDocument();
    expect(saved?.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' });
  });

  it('puts the focus on the edited comment once the edit form is gone', async () => {
    vi.spyOn(knowiiApiClient.communities.comments, 'update').mockResolvedValue({
      ok: true,
      message: '',
      data: { ...comment('c1', ada, 'First, edited'), edited_at: now },
    });
    renderSection();

    await userEvent.click(screen.getByRole('button', { name: 'Edit your comment' }));
    await userEvent.type(screen.getByRole('textbox', { name: 'Edit your comment' }), ', edited');
    await sendWithKeyboard('Save');

    const edited = document.getElementById('comment-c1');
    await waitFor(() => expect(edited).toHaveFocus());
    expect(edited).toHaveTextContent('First, edited');
    expect(screen.queryByRole('form', { name: 'Edit your comment' })).not.toBeInTheDocument();
  });

  it('puts the focus back on the "Reply" button that opened the form, after Cancel and after Escape', async () => {
    renderSection();
    const thread = screen.getByRole('list', { name: 'Replies to the comment by Ada' });

    // A reply to a reply opens the form of its thread: the focus goes back to the button of the reply
    await writeReply(within(thread).getByRole('button', { name: 'Reply to Bob' }), 'Never mind');
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByRole('form', { name: 'Reply to Ada' })).not.toBeInTheDocument();
    expect(within(thread).getByRole('button', { name: 'Reply to Bob' })).toHaveFocus();

    // Escape closes a form whose text is not changed
    await userEvent.click(screen.getByRole('button', { name: 'Reply to Ada' }));
    await userEvent.keyboard('{Escape}');
    expect(screen.queryByRole('form', { name: 'Reply to Ada' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reply to Ada' })).toHaveFocus();
  });

  it('keeps a reply form and its text when Escape is pressed after the person wrote something', async () => {
    renderSection();

    await writeReply(screen.getByRole('button', { name: 'Reply to Ada' }), 'A long answer');
    await userEvent.keyboard('{Escape}');
    expect(screen.getByRole('form', { name: 'Reply to Ada' })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Reply to Ada' })).toHaveValue('A long answer');

    // "Cancel" is the explicit way out
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByRole('form', { name: 'Reply to Ada' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reply to Ada' })).toHaveFocus();
  });

  it('puts the focus back on the "Edit" button after Cancel and after Escape', async () => {
    renderSection();

    await userEvent.click(screen.getByRole('button', { name: 'Edit your comment' }));
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByRole('form', { name: 'Edit your comment' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Edit your comment' })).toHaveFocus();

    await userEvent.click(screen.getByRole('button', { name: 'Edit your comment' }));
    expect(screen.getByRole('textbox', { name: 'Edit your comment' })).toHaveFocus();
    await userEvent.keyboard('{Escape}');
    expect(screen.queryByRole('form', { name: 'Edit your comment' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Edit your comment' })).toHaveFocus();
  });

  it('puts the focus on the new reply once when the live update comes before the response, never on the comment of another person', async () => {
    let respond: (value: Awaited<ReturnType<typeof knowiiApiClient.communities.comments.create>>) => void = () => undefined;
    vi.spyOn(knowiiApiClient.communities.comments, 'create').mockReturnValue(
      new Promise((resolve) => {
        respond = resolve;
      }),
    );
    const live = [
      comment('c1', ada, 'First', null, [comment('r1', bob, 'A reply', 'c1'), comment('r2', ada, 'Me too', 'c1')]),
      comment('c2', bob, 'Second'),
      comment('c3', bob, 'Live one'),
    ];
    vi.spyOn(knowiiApiClient.communities.comments, 'list').mockResolvedValue({ ok: true, message: '', data: onePage(live) });
    renderSection();

    const editor = await writeReply(screen.getByRole('button', { name: 'Reply to Ada' }), 'Me too');
    await sendWithKeyboard('Reply');
    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'post', comment_cuid: 'r2' }));
    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'post', comment_cuid: 'c3' }));

    // The live update shows the reply and the comment of Bob; the focus stays in the form until the response
    expect(await screen.findByText('Live one')).toBeInTheDocument();
    expect(document.getElementById('comment-r2')).toBeInTheDocument();
    expect(screen.getByRole('form', { name: 'Reply to Ada' })).toBeInTheDocument();
    expect(editor).toHaveValue('Me too');

    await act(async () => respond({ ok: true, message: '', data: comment('r2', ada, 'Me too', 'c1') }));

    const saved = document.getElementById('comment-r2');
    await waitFor(() => expect(saved).toHaveFocus());
    // One reply in the list, not two
    expect(screen.getAllByText('Me too', { selector: 'p' })).toHaveLength(1);
    expect(screen.getByRole('heading', { name: '5 comments' })).toBeInTheDocument();
  });

  it('keeps the focus on the new reply when the live update comes after the response', async () => {
    vi.spyOn(knowiiApiClient.communities.comments, 'create').mockResolvedValue({
      ok: true,
      message: '',
      data: comment('r2', ada, 'Me too', 'c1'),
    });
    const list = vi.spyOn(knowiiApiClient.communities.comments, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: onePage([
        comment('c1', ada, 'First', null, [comment('r1', bob, 'A reply', 'c1'), comment('r2', ada, 'Me too', 'c1')]),
        comment('c2', bob, 'Second'),
        comment('c3', bob, 'Live one'),
      ]),
    });
    renderSection();

    await writeReply(screen.getByRole('button', { name: 'Reply to Ada' }), 'Me too');
    await sendWithKeyboard('Reply');
    const saved = document.getElementById('comment-r2');
    await waitFor(() => expect(saved).toHaveFocus());

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'post', comment_cuid: 'c3' }));

    expect(await screen.findByText('Live one')).toBeInTheDocument();
    expect(list).toHaveBeenCalled();
    expect(saved).toHaveFocus();
    expect(screen.getAllByText('Me too', { selector: 'p' })).toHaveLength(1);
  });
});

describe('CommentSection live updates (#1180)', () => {
  const channel = 'community.community.space.space';

  it('loads the comments again when one of this post changes, and keeps the reply being typed', async () => {
    const list = vi
      .spyOn(knowiiApiClient.communities.comments, 'list')
      .mockResolvedValue({ ok: true, message: '', data: onePage([...initial, comment('c3', bob, 'Live one')]) });
    const onCountChange = renderSection();

    await userEvent.click(screen.getByRole('button', { name: 'Reply to Ada' }));
    const reply = screen.getByRole('textbox', { name: 'Reply to Ada' });
    await userEvent.type(reply, 'Half-written');

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'post', comment_cuid: 'c3' }));

    expect(await screen.findByText('Live one')).toBeInTheDocument();
    expect(list).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 0);
    expect(onCountChange).toHaveBeenLastCalledWith(4);
    expect(screen.getByRole('textbox', { name: 'Reply to Ada' })).toHaveValue('Half-written');
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

    const collapseAll = screen.getByRole('button', { name: 'Collapse all' });
    await userEvent.click(collapseAll);
    expect(screen.queryByText('First')).not.toBeInTheDocument();
    expect(screen.queryByText('Second')).not.toBeInTheDocument();
    // Nothing more to collapse: the button keeps the focus, with aria-disabled, until the focus leaves it (#1618)
    expect(collapseAll).toHaveFocus();
    expect(collapseAll).toHaveAttribute('aria-disabled', 'true');
    expect(collapseAll).not.toHaveAttribute('disabled');

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

describe('CommentSection pages (ADR 0073)', () => {
  const channel = 'community.community.space.space';
  // The first page ends inside the thread of Ada: the next page has Ada again with her next reply, then Bob
  const first = [comment('c1', ada, 'First', null, [comment('r1', bob, 'A reply', 'c1')])];
  const second = onePage(
    [comment('c1', ada, 'First', null, [comment('r2', ada, 'Another reply', 'c1')]), comment('c2', bob, 'Second')],
    null,
    4,
  );

  beforeEach(() => {
    window.localStorage.clear();
    window.history.replaceState(null, '', '/community/c/space/s/post/p');
  });
  afterEach(() => vi.restoreAllMocks());

  it('counts every comment, and "Load more comments" adds the next page to the threads', async () => {
    const list = vi.spyOn(knowiiApiClient.communities.comments, 'list').mockResolvedValue({ ok: true, message: '', data: second });
    renderSection({ initialComments: first, initialPagination: onePage(first, 2, 4).pagination });

    expect(screen.getByRole('heading', { name: '4 comments' })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Load more comments' }));

    expect(list).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 2);
    const replies = screen.getByRole('list', { name: 'Replies to the comment by Ada' });
    expect(
      within(replies)
        .getAllByRole('article')
        .map((item) => item.id),
    ).toEqual(['comment-r1', 'comment-r2']);
    expect(screen.getAllByRole('article', { name: 'Comment by Ada' })).toHaveLength(2);
    // The focus goes to the first new thread, and every comment is loaded
    await waitFor(() => expect(document.getElementById('comment-c2')).toHaveFocus());
    expect(screen.queryByRole('button', { name: 'Load more comments' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: '4 comments' })).toBeInTheDocument();
  });

  it('loads the pages until the comment of a link, and gives it the focus in its collapsed thread', async () => {
    window.localStorage.setItem('knowii:collapsed-comments:post', JSON.stringify(['c2']));
    window.history.replaceState(null, '', '/community/c/space/s/post/p#comment-c2');
    vi.spyOn(knowiiApiClient.communities.comments, 'list').mockResolvedValue({ ok: true, message: '', data: second });

    renderSection({ initialComments: first, initialPagination: onePage(first, 2, 4).pagination });

    await waitFor(() => expect(document.getElementById('comment-c2')).toHaveFocus());
    expect(screen.getByText('Second')).toBeInTheDocument();
  });

  it('loads again the pages that it shows on a live update, not more', async () => {
    const list = vi
      .spyOn(knowiiApiClient.communities.comments, 'list')
      .mockResolvedValueOnce({ ok: true, message: '', data: onePage([...first, comment('c3', bob, 'Live one')], 2, 5) });
    renderSection({ initialComments: first, initialPagination: onePage(first, 2, 4).pagination });

    act(() => emitSocketEvent(channel, 'space.comment.created', { space_cuid: 'space', post_cuid: 'post', comment_cuid: 'c3' }));

    expect(await screen.findByText('Live one')).toBeInTheDocument();
    expect(list).toHaveBeenCalledTimes(1);
    expect(list).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 0);
    expect(screen.getByRole('heading', { name: '5 comments' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Load more comments' })).toBeInTheDocument();
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

describe('CommentSection delete that keeps the replies of other people (#1565)', () => {
  afterEach(() => vi.restoreAllMocks());

  const mockDelete = () =>
    vi.spyOn(knowiiApiClient.communities.comments, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });

  // The "Delete" button of one comment (an author can have a comment and a reply in the same thread)
  async function openDeleteDialog(cuid: string) {
    const element = document.getElementById(`comment-${cuid}`);
    if (!element) {
      throw new Error(`No comment ${cuid}`);
    }
    await userEvent.click(within(element).getByRole('button', { name: /^Delete the/ }));
    return screen.findByRole('dialog', { name: 'Delete this comment?' });
  }

  it('says that the replies of other people stay and that the own comment becomes a marker', async () => {
    renderSection();

    const dialog = await openDeleteDialog('c1');

    expect(dialog).toHaveAccessibleDescription(
      'The replies of other people stay. Your comment is replaced by "This comment was deleted." This cannot be undone.',
    );
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('says that the own replies go too when there are no replies of other people', async () => {
    renderSection({ initialComments: [comment('c1', ada, 'First', null, [comment('r1', ada, 'Mine', 'c1')])] });

    const dialog = await openDeleteDialog('c1');

    expect(dialog).toHaveAccessibleDescription('Your replies under it are deleted too. This cannot be undone.');
  });

  it('says nothing more for a comment without replies, and shows no box, also to a moderator', async () => {
    renderSection({ canModerate: true });

    const dialog = await openDeleteDialog('c2');

    expect(dialog).toHaveAccessibleDescription('This cannot be undone.');
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('offers a moderator to delete the replies too, not ticked, and the text follows the box', async () => {
    const remove = mockDelete();
    renderSection({ canModerate: true, currentUserCuid: 'mod' });

    const dialog = await openDeleteDialog('c1');
    const box = within(dialog).getByRole('checkbox', { name: 'Also delete the replies (1)' });
    expect(box).not.toBeChecked();
    expect(dialog).toHaveAccessibleDescription(
      'The replies of other people stay. The comment is replaced by "This comment was deleted." This cannot be undone.',
    );

    await userEvent.click(box);
    expect(dialog).toHaveAccessibleDescription('Its replies are deleted too. This cannot be undone.');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 'c1', {
      delete_replies: true,
    });
    expect(screen.queryByText('A reply')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: '1 comment' })).toBeInTheDocument();
  });

  it('shows the marker in place after the delete, keeps the replies, and puts the focus on the marker', async () => {
    const remove = mockDelete();
    const onCountChange = renderSection();

    const dialog = await openDeleteDialog('c1');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 'c1', {});
    const marker = await screen.findByRole('article', { name: 'Deleted comment' });
    expect(marker).toHaveTextContent('This comment was deleted.');
    expect(screen.queryByText('First')).not.toBeInTheDocument();
    expect(screen.getByText('A reply')).toBeInTheDocument();
    expect(onCountChange).toHaveBeenLastCalledWith(3);
    await waitFor(() => expect(marker).toHaveFocus());
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('puts the focus on the next comment after a comment goes, and on the title after the last one', async () => {
    mockDelete();
    renderSection({ initialComments: [comment('c1', ada, 'First'), comment('c2', bob, 'Second')], canModerate: true });

    await userEvent.click(within(await openDeleteDialog('c1')).getByRole('button', { name: 'Delete' }));
    await waitFor(() => expect(document.getElementById('comment-c2')).toHaveFocus());

    await userEvent.click(within(await openDeleteDialog('c2')).getByRole('button', { name: 'Delete' }));
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Comments' })).toHaveFocus());
  });

  it('puts the focus on the comment that a deleted reply answered', async () => {
    mockDelete();
    renderSection({ initialComments: [comment('c1', bob, 'First', null, [comment('r1', ada, 'Mine', 'c1')])] });

    await userEvent.click(within(await openDeleteDialog('r1')).getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(document.getElementById('comment-c1')).toHaveFocus());
    expect(screen.queryByText('Mine')).not.toBeInTheDocument();
  });

  it('lets only a moderator delete a marker', () => {
    const marker: Comment = { ...comment('m1', ada, '', null, [comment('r9', bob, 'Still here', 'm1')]), author: null, deleted_at: now };
    renderSection({ initialComments: [marker] });
    expect(screen.queryByRole('button', { name: 'Delete the deleted comment' })).not.toBeInTheDocument();
  });

  it('deletes a marker with its replies when a moderator asks', async () => {
    const remove = mockDelete();
    const marker: Comment = { ...comment('m1', ada, '', null, [comment('r9', bob, 'Still here', 'm1')]), author: null, deleted_at: now };
    renderSection({ initialComments: [marker], canModerate: true });

    const dialog = await openDeleteDialog('m1');
    expect(dialog).toHaveAccessibleDescription('Its replies are deleted too. This cannot be undone.');
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith({ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }, 'm1', {
      delete_replies: true,
    });
    expect(screen.queryByText('Still here')).not.toBeInTheDocument();
  });
});
