import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { ReactionBar } from './ReactionBar';
import type { EmojiData } from './EmojiPicker';

const target = { communityCuid: 'c', spaceCuid: 's', postCuid: 'p' };

const emojiData: EmojiData = {
  groups: [
    {
      key: 'smileys',
      name: 'Smileys and emotion',
      emoji: [
        ['😀', 'grinning face', 'happy smile'],
        ['🦄', 'unicorn', 'fantasy horse'],
      ],
    },
    { key: 'people', name: 'People and body', emoji: [['👋', 'waving hand', 'hello wave', ['👋🏻', '👋🏼', '👋🏽', '👋🏾', '👋🏿']]] },
  ],
};
const loadEmojiData = () => Promise.resolve(emojiData);

function renderBar(initial = [{ emoji: '👍', count: 2, reacted: true }], canReact = true) {
  render(
    <AppDataProvider>
      <ReactionBar target={target} initialReactions={initial} canReact={canReact} itemLabel="this post" loadEmojiData={loadEmojiData} />
    </AppDataProvider>,
  );
}

describe('ReactionBar', () => {
  beforeEach(() => {
    window.localStorage.clear();
    Element.prototype.scrollIntoView = vi.fn();
    vi.spyOn(knowiiApiClient.users, 'frequentEmoji').mockResolvedValue({ ok: true, message: '', data: ['🦄'] });
  });
  afterEach(() => vi.restoreAllMocks());

  it('shows the reactions as toggle buttons with their state and count', () => {
    renderBar([
      { emoji: '👍', count: 2, reacted: true },
      { emoji: '😄', count: 1, reacted: false },
      { emoji: '', count: 9, reacted: false },
    ]);
    const group = screen.getByRole('group', { name: 'Reactions to this post' });
    expect(within(group).getByRole('button', { name: '👍: 2 reactions, including yours' })).toHaveAttribute('aria-pressed', 'true');
    expect(within(group).getByRole('button', { name: '😄: 1 reaction' })).toHaveAttribute('aria-pressed', 'false');
    expect(within(group).getByRole('button', { name: 'See who reacted to this post' })).toHaveTextContent('3 reactions');
  });

  it('removes the reaction of the member at once, then keeps the server summary', async () => {
    const toggle = vi
      .spyOn(knowiiApiClient.communities.reactions, 'toggle')
      .mockResolvedValue({ ok: true, message: '', data: [{ emoji: '👍', count: 1, reacted: false }] });
    renderBar();

    screen.getByRole('button', { name: /^👍/ }).focus();
    await userEvent.keyboard('{Enter}');

    expect(toggle).toHaveBeenCalledWith(target, '👍');
    expect(await screen.findByRole('button', { name: '👍: 1 reaction' })).toHaveAttribute('aria-pressed', 'false');
  });

  it('goes back when the server refuses', async () => {
    vi.spyOn(knowiiApiClient.communities.reactions, 'toggle').mockResolvedValue({ ok: false, status: 403, message: 'No', errors: {} });
    renderBar();

    await userEvent.click(screen.getByRole('button', { name: /^👍/ }));

    expect(await screen.findByRole('button', { name: '👍: 2 reactions, including yours' })).toBeInTheDocument();
  });

  it('adds any emoji with the keyboard: search, arrows, Enter; the focus goes back to the button (#1204)', async () => {
    const toggle = vi
      .spyOn(knowiiApiClient.communities.reactions, 'toggle')
      .mockResolvedValue({ ok: true, message: '', data: [{ emoji: '🦄', count: 1, reacted: true }] });
    renderBar([]);

    const trigger = screen.getByRole('button', { name: 'Add a reaction to this post' });
    trigger.focus();
    await userEvent.keyboard('{Enter}');

    const picker = await screen.findByRole('dialog', { name: 'Choose a reaction' });
    const search = within(picker).getByRole('combobox', { name: 'Search an emoji' });
    expect(search).toHaveFocus();
    expect(trigger).toHaveAttribute('aria-expanded', 'true');

    await userEvent.keyboard('horse');
    expect(await within(picker).findByRole('option', { name: 'unicorn' })).toHaveAttribute('aria-selected', 'true');
    await userEvent.keyboard('{Enter}');

    expect(toggle).toHaveBeenCalledWith(target, '🦄');
    expect(await screen.findByRole('button', { name: '🦄: 1 reaction, including yours' })).toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: 'Choose a reaction' })).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('shows the quick reactions and the frequently used emoji first, and moves in the grid with the arrows (#1204)', async () => {
    const toggle = vi.spyOn(knowiiApiClient.communities.reactions, 'toggle').mockResolvedValue({ ok: true, message: '', data: [] });
    renderBar([]);

    await userEvent.click(screen.getByRole('button', { name: 'Add a reaction to this post' }));
    const picker = await screen.findByRole('dialog', { name: 'Choose a reaction' });

    expect(within(picker).getByRole('group', { name: 'Quick reactions' })).toBeInTheDocument();
    expect(
      within(within(picker).getByRole('group', { name: 'Frequently used' })).getByRole('option', { name: 'unicorn' }),
    ).toBeInTheDocument();
    expect(within(picker).getByRole('option', { name: 'Like' })).toHaveAttribute('aria-selected', 'true');

    // Right: Love; Down: 8 options later (the 5 quick reactions, the frequent unicorn, then the group)
    await userEvent.keyboard('{ArrowRight}');
    expect(within(picker).getByRole('option', { name: 'Love' })).toHaveAttribute('aria-selected', 'true');
    await userEvent.keyboard('{ArrowDown}{Enter}');

    expect(toggle).toHaveBeenCalledWith(target, '👋');
  });

  it('applies the chosen skin tone and remembers it (#1204)', async () => {
    const toggle = vi.spyOn(knowiiApiClient.communities.reactions, 'toggle').mockResolvedValue({ ok: true, message: '', data: [] });
    renderBar([]);

    await userEvent.click(screen.getByRole('button', { name: 'Add a reaction to this post' }));
    const picker = await screen.findByRole('dialog', { name: 'Choose a reaction' });
    await userEvent.click(within(picker).getByRole('radio', { name: 'Medium skin tone' }));
    expect(window.localStorage.getItem('knowii:emoji-skin-tone')).toBe('3');

    await userEvent.type(within(picker).getByRole('combobox', { name: 'Search an emoji' }), 'wave{Enter}');

    expect(toggle).toHaveBeenCalledWith(target, '👋🏽');
  });

  it('closes the picker with Escape and gives the focus back (#1204)', async () => {
    renderBar([]);
    const trigger = screen.getByRole('button', { name: 'Add a reaction to this post' });

    await userEvent.click(trigger);
    await screen.findByRole('dialog', { name: 'Choose a reaction' });
    await userEvent.keyboard('{Escape}');

    expect(screen.queryByRole('dialog', { name: 'Choose a reaction' })).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('refuses a 21st different emoji without calling the server (#1204)', async () => {
    const toggle = vi.spyOn(knowiiApiClient.communities.reactions, 'toggle');
    const twenty = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚'];
    renderBar(twenty.map((emoji) => ({ emoji, count: 1, reacted: false })));

    await userEvent.click(screen.getByRole('button', { name: 'Add a reaction to this post' }));
    await userEvent.type(await screen.findByRole('combobox', { name: 'Search an emoji' }), 'unicorn{Enter}');

    expect(toggle).not.toHaveBeenCalled();
  });

  it('lists who reacted', async () => {
    vi.spyOn(knowiiApiClient.communities.reactions, 'whoReacted').mockResolvedValue({
      ok: true,
      message: '',
      data: [
        {
          emoji: '👍',
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

describe('ReactionBar live updates (#1180)', () => {
  it('shows the new summary when the item is loaded again', () => {
    const { rerender } = render(
      <AppDataProvider>
        <ReactionBar target={target} initialReactions={[{ emoji: '👍', count: 2, reacted: true }]} canReact itemLabel="this post" />
      </AppDataProvider>,
    );
    expect(screen.getByText('2')).toBeInTheDocument();

    rerender(
      <AppDataProvider>
        <ReactionBar target={target} initialReactions={[{ emoji: '👍', count: 5, reacted: true }]} canReact itemLabel="this post" />
      </AppDataProvider>,
    );

    expect(screen.getByText('5')).toBeInTheDocument();
  });
});
