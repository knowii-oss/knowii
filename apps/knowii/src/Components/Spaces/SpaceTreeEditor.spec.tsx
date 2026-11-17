import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { SpaceGroup } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { Toaster } from '@/Components/ui';
import { SpaceTreeEditor } from './SpaceTreeEditor';

const now = new Date();
const groups: SpaceGroup[] = [
  {
    cuid: 'start',
    name: 'Start Here',
    position: 0,
    created_at: now,
    updated_at: now,
    spaces: [
      {
        cuid: 'welcome',
        name: 'Welcome',
        slug: 'welcome',
        description: '',
        emoji: '👋',
        type: 'pages',
        visibility: 'public',
        posting_policy: 'moderators',
        position: 0,
        space_group_cuid: 'start',
        created_at: now,
        updated_at: now,
      },
      {
        cuid: 'intros',
        name: 'Introductions',
        slug: 'introductions',
        description: '',
        emoji: '',
        type: 'discussion',
        visibility: 'members',
        posting_policy: 'members',
        position: 1,
        space_group_cuid: 'start',
        created_at: now,
        updated_at: now,
      },
    ],
  },
  { cuid: 'learn', name: 'Learn', position: 1, created_at: now, updated_at: now, spaces: [] },
];

function renderEditor(onChanged = vi.fn()) {
  render(
    <AppDataProvider>
      <SpaceTreeEditor communityCuid="community" spaceGroups={groups} onChanged={onChanged} />
      <Toaster />
    </AppDataProvider>,
  );
  return onChanged;
}

describe('SpaceTreeEditor', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('shows the groups and spaces in order, and marks the moves that are not possible', () => {
    renderEditor();

    expect(screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)).toEqual(['Start Here', 'Learn']);
    // aria-disabled, not disabled: the buttons at the ends of a list can keep the focus (#1564)
    for (const name of ['Move Start Here up', 'Move Learn down', 'Move Welcome up', 'Move Introductions down']) {
      const button = screen.getByRole('button', { name });
      expect(button).toHaveAttribute('aria-disabled', 'true');
      expect(button).toBeEnabled();
    }
    expect(screen.getByRole('button', { name: 'Move Welcome down' })).not.toHaveAttribute('aria-disabled');
    expect(within(screen.getByRole('list', { name: 'Spaces of Start Here' })).getAllByRole('listitem')).toHaveLength(2);
    expect(screen.getByText('No spaces in this group yet.')).toBeInTheDocument();
  });

  it('moves a space down with the keyboard and saves the new order', async () => {
    const reorder = vi.spyOn(knowiiApiClient.communities.spaces, 'reorder').mockResolvedValue({ ok: true, message: '', data: [] });
    const onChanged = renderEditor();

    screen.getByRole('button', { name: 'Move Welcome down' }).focus();
    await userEvent.keyboard('{Enter}');

    expect(reorder).toHaveBeenCalledWith('community', 'start', ['intros', 'welcome']);
    await waitFor(() => expect(onChanged).toHaveBeenCalled());
  });

  it('keeps the focus on the pressed move button while the move runs and after the tree reloads (#1564)', async () => {
    let finish: (value: { ok: true; message: string; data: [] }) => void = () => undefined;
    const reorder = vi.spyOn(knowiiApiClient.communities.spaces, 'reorder').mockReturnValue(
      new Promise((resolve) => {
        finish = resolve;
      }),
    );
    const onChanged = vi.fn();
    const { rerender } = render(
      <AppDataProvider>
        <SpaceTreeEditor communityCuid="community" spaceGroups={groups} onChanged={onChanged} />
      </AppDataProvider>,
    );

    const down = screen.getByRole('button', { name: 'Move Welcome down' });
    down.focus();
    await userEvent.keyboard('{Enter}');

    // While busy: every move button is aria-disabled, the pressed one keeps the focus, and a second press does nothing
    expect(down).toHaveFocus();
    expect(down).toHaveAttribute('aria-disabled', 'true');
    expect(screen.getByRole('button', { name: 'Move Learn up' })).toHaveAttribute('aria-disabled', 'true');
    await userEvent.keyboard('{Enter}');
    await userEvent.click(screen.getByRole('button', { name: 'Move Learn up' }));
    expect(reorder).toHaveBeenCalledTimes(1);
    down.focus();

    finish({ ok: true, message: '', data: [] });
    await waitFor(() => expect(onChanged).toHaveBeenCalled());
    expect(down).toHaveFocus();

    // The tree reloads in the new order: React moves the list items, and the focus goes back to the pressed button
    const [start, learn] = groups;
    if (!start || !learn) {
      throw new Error('Missing test data');
    }
    const reordered: SpaceGroup[] = [{ ...start, spaces: [...(start.spaces ?? [])].reverse() }, learn];
    // Chrome drops the focus of a node that React moves; jsdom keeps it, so the test drops it
    down.blur();
    rerender(
      <AppDataProvider>
        <SpaceTreeEditor communityCuid="community" spaceGroups={reordered} onChanged={onChanged} />
      </AppDataProvider>,
    );
    expect(within(screen.getByRole('list', { name: 'Spaces of Start Here' })).getAllByRole('listitem')[1]).toHaveTextContent('Welcome');
    const atBottom = screen.getByRole('button', { name: 'Move Welcome down' });
    expect(atBottom).toHaveFocus();
    expect(atBottom).toHaveAttribute('aria-disabled', 'true');

    // At the bottom, the button does nothing
    await userEvent.keyboard('{Enter}');
    expect(reorder).toHaveBeenCalledTimes(1);
    expect(atBottom).toHaveFocus();
  });

  it('moves a space group and saves the new order', async () => {
    const reorder = vi.spyOn(knowiiApiClient.communities.spaceGroups, 'reorder').mockResolvedValue({ ok: true, message: '', data: [] });
    renderEditor();

    await userEvent.click(screen.getByRole('button', { name: 'Move Learn up' }));

    expect(reorder).toHaveBeenCalledWith('community', ['learn', 'start']);
  });

  it('asks before it deletes a space, and does not reload when the deletion fails', async () => {
    const remove = vi
      .spyOn(knowiiApiClient.communities.spaces, 'delete')
      .mockResolvedValue({ ok: false, status: 403, message: 'Not allowed', errors: {} });
    const onChanged = renderEditor();

    await userEvent.click(screen.getByRole('button', { name: 'Delete Welcome' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete Welcome?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(remove).toHaveBeenCalledWith('community', 'welcome');
    expect(onChanged).not.toHaveBeenCalled();
    expect(await screen.findByText('Not allowed')).toBeInTheDocument();
    // The dialog stays open, so the member can cancel
    expect(screen.getByRole('dialog', { name: 'Delete Welcome?' })).toBeInTheDocument();
  });
});
