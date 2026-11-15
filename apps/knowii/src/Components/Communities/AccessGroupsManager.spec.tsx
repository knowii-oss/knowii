import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AccessGroup, Author, Paginated, SpaceGroup } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { AccessGroupsManager } from './AccessGroupsManager';

const paid: AccessGroup = {
  cuid: 'g1',
  name: 'Paid members',
  description: 'With a plan',
  members_count: 1,
  spaces_count: 2,
  created_at: '',
};
const ada: Author = { cuid: 'u1', name: 'Ada Lovelace', username: 'ada-lovelace', avatar_url: null };

const page = (items: Author[]): Paginated<Author> => ({
  items,
  pagination: { limit: 20, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: items.length },
});

function renderManager(canManageAccessGroups = true, canManageAccessGroupMembers = true, canManageSpaces = false) {
  render(
    <AppDataProvider>
      <AccessGroupsManager communityCuid="c1" permissions={{ canManageAccessGroups, canManageAccessGroupMembers, canManageSpaces }} />
    </AppDataProvider>,
  );
}

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [paid] });
  vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'list').mockResolvedValue({ ok: true, message: '', data: page([ada]) });
});

describe('AccessGroupsManager (#1241)', () => {
  it('lists the groups with their counts', async () => {
    renderManager();

    const list = await screen.findByRole('list', { name: 'Access groups' });
    await within(list).findByText('Paid members');
    expect(within(list).getByText('1 member · 2 spaces')).toBeInTheDocument();
  });

  it('creates a group after checking the name', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.accessGroups, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { ...paid, cuid: 'g2', name: 'Team' } });
    renderManager();
    const form = await screen.findByRole('form', { name: 'Create an access group' });

    await userEvent.type(within(form).getByLabelText('Name'), 'T');
    await userEvent.click(within(form).getByRole('button', { name: 'Create' }));
    expect(await within(form).findByText(/Use between 2 and 64 characters/)).toBeInTheDocument();
    expect(create).not.toHaveBeenCalled();

    await userEvent.type(within(form).getByLabelText('Name'), 'eam');
    await userEvent.click(within(form).getByRole('button', { name: 'Create' }));
    await waitFor(() => expect(create).toHaveBeenCalledWith('c1', { name: 'Team', description: '' }));
  });

  it('adds a member by email and removes one', async () => {
    const add = vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'add').mockResolvedValue({ ok: true, message: '', data: ada });
    const remove = vi
      .spyOn(knowiiApiClient.communities.accessGroups.members, 'remove')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Members of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Members of Paid members' });
    await within(dialog).findByText('Ada Lovelace');

    await userEvent.type(within(dialog).getByLabelText('Username or email'), 'grace@example.com');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));
    await waitFor(() => expect(add).toHaveBeenCalledWith('c1', 'g1', { email: 'grace@example.com' }));
    await waitFor(() => expect(within(dialog).getByLabelText('Username or email')).toHaveValue(''));

    await userEvent.type(within(dialog).getByLabelText('Username or email'), '@grace-hopper');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));
    await waitFor(() => expect(add).toHaveBeenLastCalledWith('c1', 'g1', { username: 'grace-hopper' }));

    await userEvent.click(within(dialog).getByRole('button', { name: 'Remove Ada Lovelace from the group' }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'g1', 'u1'));
  });

  it('shows the server error when the person is not a member', async () => {
    vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'add').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { email: ['No member of the community matches'] },
    });
    renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Members of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Members of Paid members' });
    await userEvent.type(within(dialog).getByLabelText('Username or email'), 'stranger@example.com');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));

    expect(await within(dialog).findByText('No member of the community matches')).toBeInTheDocument();
  });

  it('lets moderators manage members only: no creation, no edit, no delete', async () => {
    renderManager(false, true);

    await screen.findByText('Paid members');
    expect(screen.queryByRole('form', { name: 'Create an access group' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Edit Paid members' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Delete Paid members' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Members of Paid members' })).toBeInTheDocument();
  });

  it('deletes a group after a confirmation', async () => {
    const destroy = vi
      .spyOn(knowiiApiClient.communities.accessGroups, 'delete')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Delete Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete Paid members?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(destroy).toHaveBeenCalledWith('c1', 'g1'));
  });
});

describe('AccessGroupsManager space groups (#1422)', () => {
  const opening: AccessGroup = { ...paid, space_groups_count: 1, space_groups: [{ cuid: 'sg1', name: 'Courses' }] };
  const spaceGroups = [
    { cuid: 'sg1', name: 'Courses', position: 1 },
    { cuid: 'sg2', name: 'Library', position: 2 },
  ] as SpaceGroup[];

  beforeEach(() => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [opening] });
    vi.spyOn(knowiiApiClient.communities.spaceGroups, 'list').mockResolvedValue({ ok: true, message: '', data: spaceGroups });
  });

  it('lists the space groups that a group opens', async () => {
    renderManager(true, true, true);

    expect(await screen.findByText('Opens the space group: Courses')).toBeInTheDocument();
  });

  it('chooses the space groups that a group opens and saves the whole list', async () => {
    const set = vi.spyOn(knowiiApiClient.communities.accessGroups.spaceGroups, 'set').mockResolvedValue({
      ok: true,
      message: '',
      data: [
        { cuid: 'sg1', name: 'Courses', position: 1 },
        { cuid: 'sg2', name: 'Library', position: 2 },
      ],
    });
    renderManager(false, false, true);

    await userEvent.click(await screen.findByRole('button', { name: 'Space groups of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Space groups of Paid members' });
    const courses = await within(dialog).findByRole('checkbox', { name: 'Courses' });
    expect(courses).toBeChecked();
    expect(within(dialog).getByRole('checkbox', { name: 'Library' })).not.toBeChecked();

    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Library' }));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(set).toHaveBeenCalledWith('c1', 'g1', ['sg1', 'sg2']));
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Space groups of Paid members' })).not.toBeInTheDocument());
  });

  it('removes a space group from the group with the keyboard, and shows the server error', async () => {
    const set = vi.spyOn(knowiiApiClient.communities.accessGroups.spaceGroups, 'set').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { space_groups: ['A space group does not exist in this community'] },
    });
    renderManager(false, false, true);

    await userEvent.click(await screen.findByRole('button', { name: 'Space groups of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Space groups of Paid members' });
    const courses = await within(dialog).findByRole('checkbox', { name: 'Courses' });
    courses.focus();
    await userEvent.keyboard(' ');
    expect(courses).not.toBeChecked();
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(set).toHaveBeenCalledWith('c1', 'g1', []));
    expect(screen.getByRole('dialog', { name: 'Space groups of Paid members' })).toBeInTheDocument();
  });

  it('hides the button from those who do not manage spaces', async () => {
    renderManager(true, true, false);

    await screen.findByText('Paid members');
    expect(screen.queryByRole('button', { name: 'Space groups of Paid members' })).not.toBeInTheDocument();
  });
});
