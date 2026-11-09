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

  it('shows the groups and spaces in order, and disables the moves that are not possible', () => {
    renderEditor();

    expect(screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)).toEqual(['Start Here', 'Learn']);
    expect(screen.getByRole('button', { name: 'Move Start Here up' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move Learn down' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move Welcome up' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move Introductions down' })).toBeDisabled();
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
