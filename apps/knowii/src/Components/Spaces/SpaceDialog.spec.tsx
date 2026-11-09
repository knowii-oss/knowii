import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Space, SpaceGroup } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { SpaceDialog } from './SpaceDialog';

const spaceGroups = [{ cuid: 'sg1', name: 'Start Here', position: 1 }] as SpaceGroup[];

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({
    ok: true,
    message: '',
    data: [
      { cuid: 'g1', name: 'Paid members', description: '', created_at: '' },
      { cuid: 'g2', name: 'Team', description: '', created_at: '' },
    ],
  });
});

function renderDialog(space?: Space) {
  const onSaved = vi.fn();
  render(
    <AppDataProvider>
      <SpaceDialog
        open
        communityCuid="c1"
        spaceGroups={spaceGroups}
        space={space}
        defaultSpaceGroupCuid="sg1"
        onClose={vi.fn()}
        onSaved={onSaved}
      />
    </AppDataProvider>,
  );
  return onSaved;
}

describe('SpaceDialog access groups (#1241)', () => {
  it('needs a group for an "Access groups only" space and sends the chosen groups', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.spaces, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { cuid: 's1', name: 'Lounge' } as Space });
    renderDialog();
    const dialog = await screen.findByRole('dialog', { name: 'New space' });

    await userEvent.type(within(dialog).getByLabelText('Name'), 'Lounge');
    await userEvent.click(within(dialog).getByRole('button', { name: /Who can read/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Access groups only' }));

    const groups = await within(dialog).findByRole('group', { name: 'Access groups that can read' });
    expect(await within(dialog).findByText('Choose at least one access group')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Create' })).toBeDisabled();

    await userEvent.click(await within(groups).findByRole('checkbox', { name: 'Team' }));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create' }));

    await waitFor(() =>
      expect(create).toHaveBeenCalledWith('c1', expect.objectContaining({ visibility: 'groups', access_groups: ['g2'] })),
    );
  });

  it('shows the current groups of a space being edited', async () => {
    renderDialog({
      cuid: 's1',
      name: 'Lounge',
      slug: 'lounge',
      description: '',
      emoji: '',
      type: 'discussion',
      visibility: 'groups',
      posting_policy: 'members',
      position: 1,
      space_group_cuid: 'sg1',
      access_groups: [{ cuid: 'g1', name: 'Paid members' }],
    } as Space);

    expect(await screen.findByRole('checkbox', { name: 'Paid members' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Team' })).not.toBeChecked();
  });
});
