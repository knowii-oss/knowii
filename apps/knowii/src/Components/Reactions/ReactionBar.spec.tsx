import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { ReactionBar } from './ReactionBar';

const target = { communityCuid: 'c', spaceCuid: 's', postCuid: 'p' };

function renderBar(initial = [{ kind: 'like', count: 2, reacted: true }], canReact = true) {
  render(
    <AppDataProvider>
      <ReactionBar target={target} initialReactions={initial} canReact={canReact} itemLabel="this post" />
    </AppDataProvider>,
  );
}

describe('ReactionBar', () => {
  afterEach(() => vi.restoreAllMocks());

  it('shows the reactions as toggle buttons with their state and count', () => {
    renderBar([
      { kind: 'like', count: 2, reacted: true },
      { kind: 'funny', count: 1, reacted: false },
      { kind: 'unknown', count: 9, reacted: false },
    ]);
    const group = screen.getByRole('group', { name: 'Reactions to this post' });
    expect(within(group).getByRole('button', { name: 'Like: 2 reactions, including yours' })).toHaveAttribute('aria-pressed', 'true');
    expect(within(group).getByRole('button', { name: 'Funny: 1 reaction' })).toHaveAttribute('aria-pressed', 'false');
    expect(within(group).getByRole('button', { name: 'See who reacted to this post' })).toHaveTextContent('3 reactions');
  });

  it('removes the reaction of the member at once, then keeps the server summary', async () => {
    const toggle = vi
      .spyOn(knowiiApiClient.communities.reactions, 'toggle')
      .mockResolvedValue({ ok: true, message: '', data: [{ kind: 'like', count: 1, reacted: false }] });
    renderBar();

    screen.getByRole('button', { name: /^Like/ }).focus();
    await userEvent.keyboard('{Enter}');

    expect(toggle).toHaveBeenCalledWith(target, 'like');
    expect(await screen.findByRole('button', { name: 'Like: 1 reaction' })).toHaveAttribute('aria-pressed', 'false');
  });

  it('goes back when the server refuses', async () => {
    vi.spyOn(knowiiApiClient.communities.reactions, 'toggle').mockResolvedValue({ ok: false, status: 403, message: 'No', errors: {} });
    renderBar();

    await userEvent.click(screen.getByRole('button', { name: /^Like/ }));

    expect(await screen.findByRole('button', { name: 'Like: 2 reactions, including yours' })).toBeInTheDocument();
  });

  it('adds a new kind from the menu with the keyboard', async () => {
    const toggle = vi
      .spyOn(knowiiApiClient.communities.reactions, 'toggle')
      .mockResolvedValue({ ok: true, message: '', data: [{ kind: 'celebrate', count: 1, reacted: true }] });
    renderBar([]);

    screen.getByRole('button', { name: 'Add a reaction to this post' }).focus();
    await userEvent.keyboard('{Enter}');
    await userEvent.click(await screen.findByRole('menuitem', { name: 'Celebrate' }));

    expect(toggle).toHaveBeenCalledWith(target, 'celebrate');
    expect(await screen.findByRole('button', { name: 'Celebrate: 1 reaction, including yours' })).toBeInTheDocument();
  });

  it('lists who reacted', async () => {
    vi.spyOn(knowiiApiClient.communities.reactions, 'whoReacted').mockResolvedValue({
      ok: true,
      message: '',
      data: [
        {
          kind: 'like',
          members: [
            { cuid: 'a', name: 'Ada', username: 'ada' },
            { cuid: 'b', name: 'Bob', username: 'bob' },
          ],
        },
      ],
    });
    renderBar();

    await userEvent.click(screen.getByRole('button', { name: 'See who reacted to this post' }));

    const dialog = await screen.findByRole('dialog', { name: 'Reactions' });
    expect(within(dialog).getByText('Ada, Bob')).toBeInTheDocument();
  });

  it('shows nothing to people who cannot react when there is no reaction', () => {
    renderBar([], false);
    expect(screen.queryByRole('group')).not.toBeInTheDocument();
  });
});
