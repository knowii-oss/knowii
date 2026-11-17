import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Author, Post } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { PostComposer } from './PostComposer';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

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
