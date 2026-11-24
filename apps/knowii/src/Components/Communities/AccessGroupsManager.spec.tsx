import type { MockInstance } from 'vitest';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AccessGroup, AccessGroupMember, Author, Paginated, SpaceGroup } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { Toaster } from '@/Components/ui';
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
const adaMember: AccessGroupMember = { ...ada, sources: ['manual'] };

const page = (items: AccessGroupMember[]): Paginated<AccessGroupMember> => ({
  items,
  pagination: { limit: 20, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: items.length },
});

function renderManager(
  canManageAccessGroups = true,
  canManageAccessGroupMembers = true,
  canManageSpaces = false,
  canManageEntitlementRules = false,
) {
  render(
    <AppDataProvider>
      <AccessGroupsManager
        communityCuid="c1"
        permissions={{ canManageAccessGroups, canManageAccessGroupMembers, canManageSpaces, canManageEntitlementRules }}
      />
      <Toaster />
    </AppDataProvider>,
  );
}

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [paid] });
  vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'list').mockResolvedValue({ ok: true, message: '', data: page([adaMember]) });
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
    // By email the server answers without the member; by username it answers with the member
    const add = vi
      .spyOn(knowiiApiClient.communities.accessGroups.members, 'add')
      .mockImplementation((_community, _group, input) =>
        Promise.resolve({ ok: true, message: '', data: 'email' in input ? undefined : ada }),
      );
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
    // The text is the same whoever has the address
    expect(await screen.findByText('If a member of the community has this address, they are now in Paid members.')).toBeInTheDocument();

    await userEvent.type(within(dialog).getByLabelText('Username or email'), '@grace-hopper');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));
    await waitFor(() => expect(add).toHaveBeenLastCalledWith('c1', 'g1', { username: 'grace-hopper' }));
    expect(await screen.findByText('Ada Lovelace added to Paid members')).toBeInTheDocument();

    await userEvent.click(within(dialog).getByRole('button', { name: 'Remove Ada Lovelace from the group' }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'g1', 'u1'));
  });

  it('shows the server error when the person is not a member', async () => {
    vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'add').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { username: ['No member of the community matches'] },
    });
    renderManager();

    await userEvent.click(await screen.findByRole('button', { name: 'Members of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Members of Paid members' });
    await userEvent.type(within(dialog).getByLabelText('Username or email'), 'stranger');
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

  it('lets moderators add a member by username only', async () => {
    const add = vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'add').mockResolvedValue({ ok: true, message: '', data: ada });
    renderManager(false, true);

    await userEvent.click(await screen.findByRole('button', { name: 'Members of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Members of Paid members' });
    expect(within(dialog).queryByLabelText('Username or email')).not.toBeInTheDocument();

    await userEvent.type(within(dialog).getByLabelText('Username'), 'grace@example.com');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));
    expect(
      await within(dialog).findByText('Only the owner and the administrators add a member by email address. Use the username.'),
    ).toBeInTheDocument();
    expect(add).not.toHaveBeenCalled();

    await userEvent.clear(within(dialog).getByLabelText('Username'));
    await userEvent.type(within(dialog).getByLabelText('Username'), '@ada-lovelace');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));
    await waitFor(() => expect(add).toHaveBeenCalledWith('c1', 'g1', { username: 'ada-lovelace' }));
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

describe('AccessGroupsManager "Show more" of the members (#657)', () => {
  type MembersAnswer = Awaited<ReturnType<typeof knowiiApiClient.communities.accessGroups.members.list>>;

  const team: AccessGroup = { ...paid, cuid: 'g2', name: 'Team', description: '' };
  const grace: AccessGroupMember = { cuid: 'u2', name: 'Grace Hopper', username: 'grace', avatar_url: null, sources: ['manual'] };
  const alan: AccessGroupMember = { cuid: 'u3', name: 'Alan Turing', username: 'alan', avatar_url: null, sources: ['manual'] };

  // The first part of a list that has more
  const firstPart = (items: AccessGroupMember[]): MembersAnswer => {
    const data = page(items);
    return { ok: true, message: '', data: { ...data, pagination: { ...data.pagination, nextOffset: 20, pageCount: 2, totalCount: 21 } } };
  };

  let list: MockInstance<typeof knowiiApiClient.communities.accessGroups.members.list>;
  // Gives the answer to "Show more" of the first group, which waits until the test calls it
  let answerMore: (answer: MembersAnswer) => void;

  beforeEach(() => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [paid, team] });
    list = vi
      .spyOn(knowiiApiClient.communities.accessGroups.members, 'list')
      .mockImplementation((_communityCuid, groupCuid, _limit, offset) => {
        if (groupCuid === 'g1' && offset === 20) {
          return new Promise<MembersAnswer>((resolve) => {
            answerMore = resolve;
          });
        }
        return Promise.resolve(firstPart([groupCuid === 'g1' ? adaMember : grace]));
      });
  });

  async function openMembersOf(name: string, member: string) {
    await userEvent.click(await screen.findByRole('button', { name: `Members of ${name}` }));
    const dialog = await screen.findByRole('dialog', { name: `Members of ${name}` });
    await within(within(dialog).getByRole('list', { name: `Members of ${name}` })).findByText(member);
    return dialog;
  }

  it('is busy while the next members load, and a second click makes no second call', async () => {
    renderManager();
    const dialog = await openMembersOf('Paid members', 'Ada Lovelace');
    const more = within(dialog).getByRole('button', { name: 'Show more' });
    expect(more).not.toHaveAttribute('aria-busy');

    await userEvent.click(more);
    expect(more).toHaveAttribute('aria-busy', 'true');
    expect(more).toHaveAttribute('aria-disabled', 'true');
    expect(list).toHaveBeenCalledTimes(2);
    expect(list).toHaveBeenLastCalledWith('c1', 'g1', 20, 20);

    await userEvent.click(more);
    more.focus();
    await userEvent.keyboard('{Enter}');
    expect(list).toHaveBeenCalledTimes(2);

    answerMore({ ok: true, message: '', data: page([grace]) });
    expect(await within(dialog).findByText('Grace Hopper')).toBeInTheDocument();
    expect(within(dialog).getByText('Ada Lovelace')).toBeInTheDocument();
    // Nothing more to load
    expect(within(dialog).queryByRole('button', { name: /Show more/ })).not.toBeInTheDocument();
  });

  it('is no longer busy after an answer that is not ok', async () => {
    renderManager();
    const dialog = await openMembersOf('Paid members', 'Ada Lovelace');
    const more = within(dialog).getByRole('button', { name: 'Show more' });

    await userEvent.click(more);
    expect(more).toHaveAttribute('aria-busy', 'true');

    answerMore({ ok: false, status: 500, message: 'The members did not load', errors: {} });
    expect(await screen.findByText('The members did not load')).toBeInTheDocument();
    await waitFor(() => expect(within(dialog).getByRole('button', { name: 'Show more' })).not.toHaveAttribute('aria-busy'));
    expect(within(dialog).getByRole('button', { name: 'Show more' })).not.toHaveAttribute('aria-disabled');

    // The member can ask again
    await userEvent.click(within(dialog).getByRole('button', { name: 'Show more' }));
    expect(list).toHaveBeenCalledTimes(3);
  });

  it('is not busy for the next group, and a late answer of the first group adds nothing to it', async () => {
    renderManager();
    const first = await openMembersOf('Paid members', 'Ada Lovelace');
    await userEvent.click(within(first).getByRole('button', { name: 'Show more' }));
    expect(within(first).getByRole('button', { name: /Show more/ })).toHaveAttribute('aria-busy', 'true');

    // The first group still loads when the manager closes it and opens the second one
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Members of Paid members' })).not.toBeInTheDocument());
    const second = await openMembersOf('Team', 'Grace Hopper');
    const more = within(second).getByRole('button', { name: 'Show more' });
    expect(more).not.toHaveAttribute('aria-busy');
    expect(more).not.toHaveAttribute('aria-disabled');

    // The answer of the first group comes late: its member is not a member of the second group
    answerMore({ ok: true, message: '', data: page([alan]) });
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
    const members = within(second).getByRole('list', { name: 'Members of Team' });
    expect(within(members).getAllByRole('listitem')).toHaveLength(1);
    expect(within(members).getByText('Grace Hopper')).toBeInTheDocument();
    expect(screen.queryByText('Alan Turing')).not.toBeInTheDocument();
    // The second group still has more to load, and its button is still not busy
    expect(within(second).getByRole('button', { name: 'Show more' })).not.toHaveAttribute('aria-busy');
  });
});

describe('AccessGroupsManager entitlement rules (#1386)', () => {
  const ruled: AccessGroup = { ...paid, entitlement_keys: ['example.paid', 'example.plus'] };

  beforeEach(() => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [ruled] });
  });

  it('shows where the members also come from', async () => {
    renderManager(true, true, false, true);

    expect(await screen.findByText('Members also come from: example.paid, example.plus')).toBeInTheDocument();
  });

  it('has no rules button without the right to manage rules', async () => {
    renderManager(true, true, false, false);
    await screen.findByText('Paid members');
    expect(screen.queryByRole('button', { name: 'Entitlement rules of Paid members' })).not.toBeInTheDocument();
  });

  it('edits the keys, one per line, and saves the whole list', async () => {
    const set = vi
      .spyOn(knowiiApiClient.communities.accessGroups.rules, 'set')
      .mockResolvedValue({ ok: true, message: '', data: { entitlement_keys: ['example.paid', 'example.vip'] } });
    renderManager(true, true, false, true);

    await userEvent.click(await screen.findByRole('button', { name: 'Entitlement rules of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Entitlement rules of Paid members' });
    const field = within(dialog).getByLabelText('Entitlement keys, one per line');
    expect(field).toHaveValue('example.paid\nexample.plus');

    await userEvent.clear(field);
    await userEvent.type(field, 'example.paid, example.vip example.paid');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(set).toHaveBeenCalledWith('c1', 'g1', ['example.paid', 'example.vip']));
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Entitlement rules of Paid members' })).not.toBeInTheDocument());
  });

  it('refuses a key with a forbidden character before any request, and shows the server error', async () => {
    const set = vi.spyOn(knowiiApiClient.communities.accessGroups.rules, 'set').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'Invalid',
      errors: { entitlement_keys: ['The keys are not valid'] },
    });
    renderManager(true, true, false, true);

    await userEvent.click(await screen.findByRole('button', { name: 'Entitlement rules of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Entitlement rules of Paid members' });
    const field = within(dialog).getByLabelText('Entitlement keys, one per line');
    await userEvent.clear(field);
    await userEvent.type(field, 'bad/key');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));
    expect(await within(dialog).findByText('A key holds letters, digits, dots, dashes and underscores only')).toBeInTheDocument();
    expect(set).not.toHaveBeenCalled();

    await userEvent.clear(field);
    await userEvent.type(field, 'good.key');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));
    expect(await within(dialog).findByText('The keys are not valid')).toBeInTheDocument();
  });

  it('empties the rules with an empty list', async () => {
    const set = vi
      .spyOn(knowiiApiClient.communities.accessGroups.rules, 'set')
      .mockResolvedValue({ ok: true, message: '', data: { entitlement_keys: [] } });
    renderManager(true, true, false, true);

    await userEvent.click(await screen.findByRole('button', { name: 'Entitlement rules of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Entitlement rules of Paid members' });
    await userEvent.clear(within(dialog).getByLabelText('Entitlement keys, one per line'));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(set).toHaveBeenCalledWith('c1', 'g1', []));
  });
});

describe('AccessGroupsManager member sources (#1386)', () => {
  const byHand: AccessGroupMember = { ...ada, sources: ['manual'] };
  const byRule: AccessGroupMember = { cuid: 'u2', name: 'Grace Hopper', username: 'grace', avatar_url: null, sources: ['by_rule'] };
  const both: AccessGroupMember = { cuid: 'u3', name: 'Alan Turing', username: 'alan', avatar_url: null, sources: ['manual', 'by_rule'] };

  beforeEach(() => {
    vi.spyOn(knowiiApiClient.communities.accessGroups.members, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: page([byHand, byRule, both]),
    });
  });

  async function openMembers() {
    renderManager();
    await userEvent.click(await screen.findByRole('button', { name: 'Members of Paid members' }));
    const dialog = await screen.findByRole('dialog', { name: 'Members of Paid members' });
    await within(dialog).findByText('Ada Lovelace');
    return dialog;
  }

  it('shows the source of each membership', async () => {
    const dialog = await openMembers();

    expect(within(dialog).getByText('Added by hand')).toBeInTheDocument();
    expect(within(dialog).getByText('By an entitlement rule')).toBeInTheDocument();
    expect(within(dialog).getByText('Added by hand and by an entitlement rule')).toBeInTheDocument();
  });

  it('offers no removal for a member that only a rule gives', async () => {
    const dialog = await openMembers();

    expect(within(dialog).getByRole('button', { name: 'Remove Ada Lovelace from the group' })).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Remove Grace Hopper from the group' })).not.toBeInTheDocument();
  });

  it('says that the member stays when a rule keeps them in the group', async () => {
    const remove = vi
      .spyOn(knowiiApiClient.communities.accessGroups.members, 'remove')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    const dialog = await openMembers();

    await userEvent.click(within(dialog).getByRole('button', { name: 'Remove Alan Turing from the group' }));

    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'g1', 'u3'));
    expect(
      await screen.findByText(/Alan Turing is no longer added by hand. They stay in Paid members because of an entitlement rule/),
    ).toBeInTheDocument();
    // The member is still listed, now by the rule only, and there is nothing left to remove by hand
    expect(within(dialog).queryByRole('button', { name: 'Remove Alan Turing from the group' })).not.toBeInTheDocument();
    expect(within(dialog).getAllByText('By an entitlement rule')).toHaveLength(2);
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
