import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Space, SpaceGroup } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { SpaceDialog } from './SpaceDialog';

// Built at run time: a literal script URL is a lint error
const SCRIPT_URL = ['javascript', 'alert(1)'].join(':');

const spaceGroups = [
  { cuid: 'sg1', name: 'Start Here', position: 1 },
  { cuid: 'sg2', name: 'Courses', position: 2 },
  { cuid: 'sg3', name: 'Library', position: 3 },
] as SpaceGroup[];

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({
    ok: true,
    message: '',
    data: [
      { cuid: 'g1', name: 'Paid members', description: '', created_at: '', space_groups: [{ cuid: 'sg3', name: 'Library' }] },
      { cuid: 'g2', name: 'Team', description: '', created_at: '', space_groups: [{ cuid: 'sg2', name: 'Courses' }] },
      {
        cuid: 'g3',
        name: 'Alumni',
        description: '',
        created_at: '',
        space_groups: [
          { cuid: 'sg2', name: 'Courses' },
          { cuid: 'sg3', name: 'Library' },
        ],
      },
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

const groupsSpace = {
  cuid: 's1',
  name: 'Lounge',
  slug: 'lounge',
  description: '',
  emoji: '',
  type: 'discussion',
  visibility: 'groups',
  posting_policy: 'members',
  position: 1,
  space_group_cuid: 'sg2',
  access_groups: [],
} as Space;

describe('SpaceDialog access groups of the space group (#1422)', () => {
  it('shows which access groups open the space and where each one comes from', async () => {
    renderDialog({
      ...groupsSpace,
      access_groups: [
        { cuid: 'g3', name: 'Alumni' },
        { cuid: 'g1', name: 'Paid members' },
      ],
    });
    const dialog = await screen.findByRole('dialog', { name: 'Edit Lounge' });

    const list = await within(dialog).findByRole('list', { name: 'Access groups that open this space' });
    const items = within(list).getAllByRole('listitem');
    expect(items.map((item) => item.textContent)).toEqual([
      'Paid members from this space',
      'Team from its space group',
      'Alumni from this space and its space group',
    ]);
  });

  it('accepts a groups space with no group of its own when its space group has one', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.spaces, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { cuid: 's2', name: 'Week one' } as Space });
    renderDialog();
    const dialog = await screen.findByRole('dialog', { name: 'New space' });

    await userEvent.type(within(dialog).getByLabelText('Name'), 'Week one');
    await userEvent.click(within(dialog).getByRole('button', { name: /Who can read/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Access groups only' }));
    // Start Here has no access group: the space needs one of its own
    expect(await within(dialog).findByText('Choose at least one access group')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Create' })).toBeDisabled();

    // Courses is opened by Team and Alumni
    await userEvent.click(within(dialog).getByRole('button', { name: /^Space group/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Courses' }));
    await waitFor(() => expect(within(dialog).queryByText('Choose at least one access group')).not.toBeInTheDocument());
    const opening = await within(dialog).findByRole('list', { name: 'Access groups that open this space' });
    expect(within(opening).getByText('Team')).toBeInTheDocument();

    await userEvent.click(within(dialog).getByRole('button', { name: 'Create' }));
    await waitFor(() => expect(create).toHaveBeenCalledWith('c1', expect.objectContaining({ visibility: 'groups', access_groups: [] })));
  });

  it('warns, names the groups that gain and lose the space, and asks to confirm before a move', async () => {
    const update = vi
      .spyOn(knowiiApiClient.communities.spaces, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...groupsSpace, space_group_cuid: 'sg3' } });
    renderDialog(groupsSpace);
    const dialog = await screen.findByRole('dialog', { name: 'Edit Lounge' });
    await within(dialog).findByRole('checkbox', { name: 'Team' });
    expect(within(dialog).queryByText('Moving this space changes who can read it')).not.toBeInTheDocument();

    await userEvent.click(within(dialog).getByRole('button', { name: /^Space group/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Library' }));

    expect(await within(dialog).findByText('Moving this space changes who can read it')).toBeInTheDocument();
    expect(within(dialog).getByText(/^Will gain it:/)).toHaveTextContent('Will gain it: Paid members.');
    expect(within(dialog).getByText(/^Will lose it:/)).toHaveTextContent('Will lose it: Team.');
    const save = within(dialog).getByRole('button', { name: 'Save' });
    expect(save).toBeDisabled();

    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'I understand, move the space' }));
    expect(save).toBeEnabled();
    await userEvent.click(save);

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 's1', expect.objectContaining({ space_group_cuid: 'sg3' })));
  });

  it('asks to confirm again when the move changes', async () => {
    renderDialog(groupsSpace);
    const dialog = await screen.findByRole('dialog', { name: 'Edit Lounge' });
    await within(dialog).findByRole('checkbox', { name: 'Team' });

    await userEvent.click(within(dialog).getByRole('button', { name: /^Space group/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Library' }));
    await userEvent.click(await within(dialog).findByRole('checkbox', { name: 'I understand, move the space' }));
    expect(within(dialog).getByRole('button', { name: 'Save' })).toBeEnabled();

    // Back to the space group of before: nothing changes, nothing to confirm
    await userEvent.click(within(dialog).getByRole('button', { name: /^Space group/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Courses' }));
    expect(within(dialog).queryByText('Moving this space changes who can read it')).not.toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Save' })).toBeEnabled();
  });

  it('does not warn for a space that every member reads', async () => {
    renderDialog({ ...groupsSpace, visibility: 'members' });
    const dialog = await screen.findByRole('dialog', { name: 'Edit Lounge' });

    await userEvent.click(within(dialog).getByRole('button', { name: /^Space group/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Courses' }));

    expect(within(dialog).queryByText('Moving this space changes who can read it')).not.toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Save' })).toBeEnabled();
  });
});

describe('SpaceDialog link spaces (#1294)', () => {
  it('asks for the address of a link space, hides "Who can post" and sends the URL', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.spaces, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { cuid: 's1', name: 'Shop' } as Space });
    renderDialog();
    const dialog = await screen.findByRole('dialog', { name: 'New space' });

    expect(within(dialog).queryByLabelText('Address of the page')).not.toBeInTheDocument();
    await userEvent.type(within(dialog).getByLabelText('Name'), 'Shop');
    await userEvent.click(within(dialog).getByRole('button', { name: /^Type/ }));
    await userEvent.click(await screen.findByRole('option', { name: /^Link/ }));

    expect(within(dialog).queryByRole('button', { name: /Who can post/ })).not.toBeInTheDocument();
    const url = within(dialog).getByLabelText('Address of the page');
    await userEvent.type(url, SCRIPT_URL);
    expect(await within(dialog).findByText('Enter a full address that starts with https:// or http://')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Create' })).toBeDisabled();

    await userEvent.clear(url);
    await userEvent.type(url, 'https://shop.example.com');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create' }));

    await waitFor(() =>
      expect(create).toHaveBeenCalledWith('c1', expect.objectContaining({ type: 'link', url: 'https://shop.example.com' })),
    );
  });

  it('does not send a URL for the other types', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.spaces, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { cuid: 's1', name: 'Lounge' } as Space });
    renderDialog();
    const dialog = await screen.findByRole('dialog', { name: 'New space' });

    await userEvent.type(within(dialog).getByLabelText('Name'), 'Lounge');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create' }));

    await waitFor(() => expect(create).toHaveBeenCalledTimes(1));
    expect(create.mock.calls[0]?.[1]).not.toHaveProperty('url');
  });
});

describe('SpaceDialog public spaces (#1387)', () => {
  it('warns the manager who chooses "public", and says that a chat space stays for signed-in people', async () => {
    renderDialog();
    const dialog = await screen.findByRole('dialog', { name: 'New space' });

    expect(within(dialog).queryByRole('note')).not.toBeInTheDocument();
    await userEvent.click(within(dialog).getByRole('button', { name: /Who can read/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Public: anyone can read' }));

    const note = within(dialog).getByRole('note');
    expect(note).toHaveTextContent(
      'Anyone on the internet can read this space, without an account, with the names and pictures of the authors.',
    );
    expect(note).not.toHaveTextContent('A chat space stays for signed-in people.');

    await userEvent.click(within(dialog).getByRole('button', { name: /^Type/ }));
    await userEvent.click(await screen.findByRole('option', { name: /^Chat/ }));
    expect(within(dialog).getByRole('note')).toHaveTextContent('A chat space stays for signed-in people.');
  });
});
