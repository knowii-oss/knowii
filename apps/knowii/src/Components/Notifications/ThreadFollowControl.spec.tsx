import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { spyOnFollowApi } from '@/test-utils/notifications';
import { ThreadFollowControl } from './ThreadFollowControl';

describe('the control of a thread (#1497)', () => {
  let api: ReturnType<typeof spyOnFollowApi>;

  beforeEach(() => {
    api = spyOnFollowApi();
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const renderPost = () => render(<ThreadFollowControl communityCuid="c1" spaceCuid="s1" kind="post" cuid="p1" />);

  it('shows nothing until the state is known, and reads it once for the thread', () => {
    api.getPost.mockReturnValue(new Promise(() => undefined));
    const { container } = renderPost();

    expect(container).toBeEmptyDOMElement();
    expect(api.getPost).toHaveBeenCalledTimes(1);
    expect(api.getPost).toHaveBeenCalledWith('c1', 's1', 'p1');
  });

  it('shows nothing when the state cannot be read (a person who may not follow it)', async () => {
    api.getPost.mockResolvedValue({ ok: false, status: 403, message: 'No', errors: {} });
    const { container } = renderPost();

    await waitFor(() => expect(api.getPost).toHaveBeenCalled());
    expect(container).toBeEmptyDOMElement();
  });

  it.each([
    [{ state: 'following', reason: 'wrote' }, 'Following (you wrote this)', 'Stop following', true],
    [{ state: 'following', reason: 'replied' }, 'Following (you replied)', 'Stop following', true],
    [{ state: 'following', reason: 'manual' }, 'Following', 'Stop following', true],
    [{ state: null, reason: null }, 'Not following', 'Follow', true],
    [{ state: 'stopped', reason: 'replied' }, 'Not following', 'Follow', true],
    [{ state: 'muted', reason: 'manual' }, 'Muted', 'Follow', false],
  ] as const)('says %j as "%s", with "%s" (mute offered: %s)', async (state, text, action, canMute) => {
    api.getPost.mockResolvedValue(api.follow(state.state, state.reason));
    renderPost();

    const group = await screen.findByRole('group', { name: 'Notifications for this thread' });
    expect(group).toHaveTextContent(text);
    expect(screen.getByRole('button', { name: action })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Mute this thread' }) !== null).toBe(canMute);
  });

  it('follows, then stops, then mutes, and says the new state in a status region', async () => {
    renderPost();
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Follow' }));
    expect(api.followPost).toHaveBeenCalledWith('c1', 's1', 'p1');
    expect(await screen.findByRole('button', { name: 'Stop following' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Following');

    api.stopPost.mockResolvedValue(api.follow('stopped', 'manual'));
    await user.click(screen.getByRole('button', { name: 'Stop following' }));
    expect(api.stopPost).toHaveBeenCalledWith('c1', 's1', 'p1');
    expect(await screen.findByRole('button', { name: 'Follow' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Not following');

    await user.click(screen.getByRole('button', { name: 'Mute this thread' }));
    expect(api.mutePost).toHaveBeenCalledWith('c1', 's1', 'p1');
    await waitFor(() => expect(screen.getByRole('group')).toHaveTextContent('Muted'));
    expect(screen.queryByRole('button', { name: 'Mute this thread' })).toBeNull();
    expect(screen.getByRole('status')).toHaveTextContent('Muted');
  });

  it('works on a chat thread by its first message', async () => {
    render(<ThreadFollowControl communityCuid="c1" spaceCuid="s1" kind="message" cuid="m1" />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Follow' }));

    expect(api.getMessage).toHaveBeenCalledWith('c1', 's1', 'm1');
    expect(api.followMessage).toHaveBeenCalledWith('c1', 's1', 'm1');
    await user.click(await screen.findByRole('button', { name: 'Mute this thread' }));
    expect(api.muteMessage).toHaveBeenCalledWith('c1', 's1', 'm1');
  });

  it('keeps the old state and says "Not saved. Retry." in an alert, with the focus on the button', async () => {
    api.followPost.mockResolvedValue({ ok: false, status: 500, message: 'Failed', errors: {} });
    renderPost();
    const user = userEvent.setup();

    const follow = await screen.findByRole('button', { name: 'Follow' });
    await user.click(follow);

    expect(await screen.findByRole('alert')).toHaveTextContent('Not saved. Retry.');
    expect(screen.getByRole('group')).toHaveTextContent('Not following');
    expect(follow).toHaveFocus();

    api.followPost.mockResolvedValue(api.follow('following', 'manual'));
    await user.click(follow);
    await waitFor(() => expect(screen.queryByRole('alert')).toBeNull());
  });

  it('never uses disabled: a second press while the first waits is ignored', async () => {
    let resolve: (value: ReturnType<typeof api.follow>) => void = () => undefined;
    api.followPost.mockReturnValue(new Promise((done) => (resolve = done)));
    renderPost();
    const user = userEvent.setup();
    const follow = await screen.findByRole('button', { name: 'Follow' });

    await user.click(follow);
    await user.click(follow);

    expect(follow).not.toBeDisabled();
    expect(follow).toHaveAttribute('aria-busy', 'true');
    expect(api.followPost).toHaveBeenCalledTimes(1);
    await act(async () => resolve(api.follow('following', 'manual')));
  });

  it('has no automated violation (axe)', async () => {
    api.getPost.mockResolvedValue(api.follow('following', 'replied'));
    const { container } = renderPost();
    await screen.findByRole('group');

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } });

    expect(results.violations).toEqual([]);
  });
});
