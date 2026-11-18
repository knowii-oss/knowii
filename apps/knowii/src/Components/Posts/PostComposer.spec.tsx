import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Author, Post } from '@knowii/common';
import {
  AppDataProvider,
  KEPT_TEXT_KEY_PREFIX,
  KEPT_TEXT_WRITE_INTERVAL_MS,
  knowiiApiClient,
  readKeptText,
  writeKeptText,
} from '@knowii/common';
import { PostComposer } from './PostComposer';

// The signed-in account: its kept texts are keyed by its cuid (#1622)
const account = vi.hoisted(() => ({ cuid: 'ada1' }));

vi.mock('@inertiajs/react', () => ({
  router: { reload: vi.fn() },
  usePage: () => ({ props: { auth: { user: { cuid: account.cuid } } } }),
}));

const saved = { cuid: 'p1', title: 'Hello', slug: 'hello' } as Post;

function renderComposer(props: { canModerate?: boolean; post?: Post } = {}) {
  render(
    <AppDataProvider>
      <PostComposer
        open
        communityCuid="c1"
        spaceCuid="s1"
        spaceName="Open discussion"
        canModerate={props.canModerate ?? false}
        post={props.post}
        onClose={vi.fn()}
        onSaved={vi.fn()}
      />
    </AppDataProvider>,
  );
}

async function fillAndPublish(label = 'Publish') {
  const dialog = await screen.findByRole('dialog');
  await userEvent.type(within(dialog).getByLabelText('Title'), 'Hello');
  const body = dialog.querySelector('#post-body');
  expect(body).not.toBeNull();
  await userEvent.type(body as HTMLElement, 'A first post');
  await userEvent.click(within(dialog).getByRole('button', { name: label }));
}

describe('"Tell the followers of this space now" in the post composer (#1497)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.localStorage.clear();
  });

  it('is for the people who can moderate the space, and off by default', async () => {
    renderComposer({ canModerate: true });
    const dialog = await screen.findByRole('dialog');

    const box = within(dialog).getByRole('checkbox', { name: /Tell the followers of this space now/ });
    expect(box).not.toBeChecked();
    expect(within(dialog).getByText(/Without it, the post tells them after 5 minutes/)).toBeInTheDocument();
  });

  it('is not there for a member who cannot moderate the space', async () => {
    renderComposer({ canModerate: false });
    const dialog = await screen.findByRole('dialog');

    expect(within(dialog).queryByRole('checkbox', { name: /Tell the followers/ })).toBeNull();
  });

  it('is not there when a post is edited', async () => {
    renderComposer({ canModerate: true, post: { ...saved, body: 'Body' } });
    const dialog = await screen.findByRole('dialog');

    expect(within(dialog).queryByRole('checkbox', { name: /Tell the followers/ })).toBeNull();
  });

  it('sends nothing more when it is off', async () => {
    const create = vi.spyOn(knowiiApiClient.communities.posts, 'create').mockResolvedValue({ ok: true, message: '', data: saved });
    renderComposer({ canModerate: true });

    await fillAndPublish();

    await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
    expect(create.mock.calls[0]?.[2]).toEqual({ title: 'Hello', body: 'A first post' });
  });

  it('sends notify_now when it is on', async () => {
    const create = vi.spyOn(knowiiApiClient.communities.posts, 'create').mockResolvedValue({ ok: true, message: '', data: saved });
    renderComposer({ canModerate: true });
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(within(dialog).getByRole('checkbox', { name: /Tell the followers of this space now/ }));

    await fillAndPublish();

    await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
    expect(create.mock.calls[0]?.[2]).toEqual({ title: 'Hello', body: 'A first post', notify_now: true });
  });
});

/**
 * The composer with its trigger, as in a space: onClose closes it
 */
function ComposerHarness({ post, onClose }: { post?: Post; onClose: () => void }) {
  const [open, setOpen] = useState(true);
  return (
    <AppDataProvider>
      <PostComposer
        open={open}
        communityCuid="c1"
        spaceCuid="s1"
        spaceName="Open discussion"
        post={post}
        onClose={() => {
          setOpen(false);
          onClose();
        }}
        onSaved={vi.fn()}
      />
    </AppDataProvider>
  );
}

describe('the post composer asks before it loses the text (#1563)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.localStorage.clear();
  });

  it('asks on Escape after the member typed, and keeps the text with "Keep writing"', async () => {
    const onClose = vi.fn();
    render(<ComposerHarness onClose={onClose} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });
    const title = within(dialog).getByLabelText('Title');
    await userEvent.type(title, 'Hello');

    await userEvent.keyboard('{Escape}');

    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    expect(onClose).not.toHaveBeenCalled();
    await userEvent.click(within(question).getByRole('button', { name: 'Keep writing' }));
    await waitFor(() => expect(title).toHaveFocus());
    expect(title).toHaveValue('Hello');
    expect(onClose).not.toHaveBeenCalled();
  });

  it('asks on "Cancel" after the member typed, and closes with "Discard"', async () => {
    const onClose = vi.fn();
    render(<ComposerHarness onClose={onClose} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });
    await userEvent.type(within(dialog).getByLabelText('Title'), 'Hello');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    await userEvent.click(within(question).getByRole('button', { name: 'Discard' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('closes at once on Escape when the member typed nothing', async () => {
    const onClose = vi.fn();
    render(<ComposerHarness onClose={onClose} />);
    await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    await userEvent.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledOnce();
    expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument();
  });

  it('closes at once on Escape when a post opened for an edit did not change', async () => {
    const onClose = vi.fn();
    render(<ComposerHarness post={{ ...saved, body: 'A first post' }} onClose={onClose} />);
    await screen.findByRole('dialog', { name: 'Edit the post' });

    await userEvent.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledOnce();
    expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument();
  });

  it('closes with no question after a post is published', async () => {
    vi.spyOn(knowiiApiClient.communities.posts, 'create').mockResolvedValue({ ok: true, message: '', data: saved });
    const onClose = vi.fn();
    render(<ComposerHarness onClose={onClose} />);

    await fillAndPublish();

    await waitFor(() => expect(onClose).toHaveBeenCalledOnce());
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('closes the mention list on Escape without the question', async () => {
    vi.spyOn(knowiiApiClient.communities.members, 'search').mockResolvedValue([{ username: 'ada', name: 'Ada' } as Author]);
    const onClose = vi.fn();
    render(<ComposerHarness onClose={onClose} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });
    const body = within(dialog).getByRole('combobox', { name: 'Post' });
    await userEvent.type(body, 'Thanks @a');
    await within(dialog).findByRole('option');

    await userEvent.keyboard('{Escape}');

    expect(body).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument();
    expect(screen.getByRole('dialog', { name: 'New post in Open discussion' })).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });
});

const adaInSpace = { account: 'ada1', kind: 'post', place: 's1' } as const;

function keepPostText(fields: Record<string, string | boolean>, place = adaInSpace) {
  writeKeptText(place, { fields, changedAt: Date.now(), base: null });
}

// More than the interval between two writes
const waitForWrite = () => new Promise((resolve) => setTimeout(resolve, KEPT_TEXT_WRITE_INTERVAL_MS + 200));

describe('the post composer keeps the text that was not sent (#1622)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.localStorage.clear();
    account.cuid = 'ada1';
  });

  it('keeps the text while the member writes, at most once a second', async () => {
    const setItem = vi.spyOn(Storage.prototype, 'setItem');
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    await userEvent.type(within(dialog).getByLabelText('Title'), 'Hello');
    await userEvent.type(within(dialog).getByRole('combobox', { name: 'Post' }), 'A long text');
    await waitFor(() => expect(readKeptText(adaInSpace)?.fields).toEqual({ title: 'Hello', body: 'A long text', notify_now: false }));

    const writes = setItem.mock.calls.filter(([key]) => key.startsWith(KEPT_TEXT_KEY_PREFIX));
    expect(writes).toHaveLength(1);
  });

  it('writes nothing when nothing changed, and removes the entry when the form is empty again', async () => {
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });
    await waitForWrite();
    expect(window.localStorage.length).toBe(0);

    const title = within(dialog).getByLabelText('Title');
    await userEvent.type(title, 'Hi');
    await waitFor(() => expect(readKeptText(adaInSpace)).not.toBeNull());
    await userEvent.clear(title);
    await waitFor(() => expect(readKeptText(adaInSpace)).toBeNull());
  });

  it('keeps nothing for an edit of a post', async () => {
    render(<ComposerHarness post={{ ...saved, body: 'A first post' }} onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'Edit the post' });

    await userEvent.type(within(dialog).getByLabelText('Title'), ' again');
    await waitForWrite();

    expect(window.localStorage.length).toBe(0);
  });

  it('shows a kept text with the line, read as a description of the dialog', async () => {
    keepPostText({ title: 'Kept title', body: 'Kept body', notify_now: false });
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    expect(within(dialog).getByLabelText('Title')).toHaveValue('Kept title');
    expect(within(dialog).getByRole('combobox', { name: 'Post' })).toHaveValue('Kept body');
    expect(within(dialog).getByText('We kept the text that you did not send.')).toBeInTheDocument();
    await waitFor(() => expect(dialog).toHaveAccessibleDescription('We kept the text that you did not send.'));
    // Not an alert: the focus stays on the title
    expect(within(dialog).queryByRole('alert')).toBeNull();
    await waitFor(() => expect(within(dialog).getByLabelText('Title')).toHaveFocus());
  });

  it('gives back the choice "Tell the followers of this space now" with the text', async () => {
    keepPostText({ title: 'Kept title', body: 'Kept body', notify_now: true });
    renderComposer({ canModerate: true });
    const dialog = await screen.findByRole('dialog');

    expect(within(dialog).getByRole('checkbox', { name: /Tell the followers of this space now/ })).toBeChecked();
  });

  it('never shows the kept text of another account', async () => {
    keepPostText({ title: 'Ada writes', body: 'Secret' });
    account.cuid = 'bob1';
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    expect(within(dialog).getByLabelText('Title')).toHaveValue('');
    expect(within(dialog).queryByText('We kept the text that you did not send.')).toBeNull();
    expect(readKeptText(adaInSpace)).not.toBeNull();
  });

  it('empties the form and removes the entry with "Discard", and the focus goes to the title', async () => {
    keepPostText({ title: 'Kept title', body: 'Kept body' });
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    await userEvent.click(within(dialog).getByRole('button', { name: 'Discard' }));

    const title = within(dialog).getByLabelText('Title');
    expect(title).toHaveValue('');
    expect(within(dialog).getByRole('combobox', { name: 'Post' })).toHaveValue('');
    expect(within(dialog).queryByText('We kept the text that you did not send.')).toBeNull();
    expect(title).toHaveFocus();
    expect(readKeptText(adaInSpace)).toBeNull();
  });

  it('asks before Escape closes a form with a kept text, and removes the text only after "Discard"', async () => {
    keepPostText({ title: 'Kept title', body: 'Kept body' });
    const onClose = vi.fn();
    render(<ComposerHarness onClose={onClose} />);
    await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    await userEvent.keyboard('{Escape}');
    const question = await screen.findByRole('dialog', { name: 'Discard your text?' });
    expect(onClose).not.toHaveBeenCalled();
    expect(readKeptText(adaInSpace)).not.toBeNull();

    await userEvent.click(within(question).getByRole('button', { name: 'Discard' }));

    await waitFor(() => expect(onClose).toHaveBeenCalledOnce());
    expect(readKeptText(adaInSpace)).toBeNull();
  });

  it('removes the kept text after a publish', async () => {
    keepPostText({ title: 'Kept title', body: 'Kept body' });
    const create = vi.spyOn(knowiiApiClient.communities.posts, 'create').mockResolvedValue({ ok: true, message: '', data: saved });
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    await userEvent.click(within(dialog).getByRole('button', { name: 'Publish' }));

    await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
    expect(create.mock.calls[0]?.[2]).toEqual({ title: 'Kept title', body: 'Kept body' });
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitForWrite();
    expect(readKeptText(adaInSpace)).toBeNull();
  });

  it('keeps the text when a publish fails', async () => {
    keepPostText({ title: 'Kept title', body: 'Kept body' });
    vi.spyOn(knowiiApiClient.communities.posts, 'create').mockResolvedValue({
      ok: false,
      status: 503,
      message: 'The server is busy',
      errors: {},
    });
    render(<ComposerHarness onClose={vi.fn()} />);
    const dialog = await screen.findByRole('dialog', { name: 'New post in Open discussion' });

    await userEvent.click(within(dialog).getByRole('button', { name: 'Publish' }));

    await waitFor(() => expect(within(dialog).getByRole('button', { name: 'Publish' })).not.toHaveAttribute('aria-busy', 'true'));
    expect(screen.getByRole('dialog', { name: 'New post in Open discussion' })).toBeInTheDocument();
    expect(readKeptText(adaInSpace)?.fields).toEqual({ title: 'Kept title', body: 'Kept body' });
  });
});
