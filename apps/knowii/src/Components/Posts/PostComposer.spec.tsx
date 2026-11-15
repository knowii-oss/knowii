import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Post } from '@knowii/common';
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
