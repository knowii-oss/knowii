import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type {
  AccessGroup,
  CommunityInvitation,
  CommunityJoinBlock,
  CommunityMember,
  CommunityRole,
  MemberTag,
  Paginated,
} from '@knowii/common';
import { AppDataProvider, canActOnRole, grantableRoles, knowiiApiClient } from '@knowii/common';
import { INVITATION_WORDS } from './invitation-words';
import { CommunityMembersManager } from './CommunityMembersManager';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: Record<string, string>) => `/community/${params['communitySlug']}/member/${params['username']}`,
    writable: true,
  });
});

const member = (cuid: string, name: string, role: CommunityRole): CommunityMember => ({
  cuid,
  name,
  username: name.toLowerCase(),
  // The entries of the member list hold the headline (#1688). The member management does not show it
  headline: `The headline of ${name}`,
  role,
  member_since: '2026-11-01T10:00:00.000000Z',
  tags: [],
  in_member_list: true,
});

const members = [
  member('o', 'Olivia', 'owner'),
  member('a', 'Alan', 'admin'),
  member('m', 'Margaret', 'moderator'),
  member('u', 'Ursula', 'member'),
];

const page = <T,>(items: T[], nextOffset: number | null = null): Paginated<T> => ({
  items,
  pagination: {
    limit: 20,
    offset: 0,
    previousOffset: null,
    nextOffset,
    currentPage: 1,
    pageCount: 1,
    totalCount: items.length,
  } as Paginated<T>['pagination'],
});

const invitation: CommunityInvitation = {
  cuid: 'i1',
  email: 'grace@example.com',
  role: 'moderator',
  created_at: new Date().toISOString(),
  expires_at: new Date().toISOString(),
  is_expired: false,
  bounced_at: null,
  bounce_type: null,
  access_groups: [],
  member_tags: [],
};

const allRights = { canAddCommunityMembers: true, canUpdateCommunityMembers: true, canRemoveCommunityMembers: true };

function renderManager(currentRole: CommunityRole, permissions = allRights) {
  render(
    <AppDataProvider>
      <CommunityMembersManager communityCuid="c1" communitySlug="tests" currentRole={currentRole} permissions={permissions} />
    </AppDataProvider>,
  );
}

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue({ ok: true, message: '', data: page(members) });
  vi.spyOn(knowiiApiClient.communities.invitations, 'list').mockResolvedValue({ ok: true, message: '', data: page([invitation]) });
  vi.spyOn(knowiiApiClient.communities.members, 'joinBlocks').mockResolvedValue({ ok: true, message: '', data: [] });
});

describe('role hierarchy helpers (#1242)', () => {
  it('acts only on lower roles and gives only lower roles', () => {
    expect(canActOnRole('owner', 'admin')).toBe(true);
    expect(canActOnRole('admin', 'admin')).toBe(false);
    expect(canActOnRole('admin', 'owner')).toBe(false);
    expect(canActOnRole('moderator', 'member')).toBe(true);
    expect(grantableRoles('owner')).toEqual(['admin', 'moderator', 'member']);
    expect(grantableRoles('admin')).toEqual(['moderator', 'member']);
    expect(grantableRoles('moderator')).toEqual(['member']);
    expect(grantableRoles('member')).toEqual([]);
  });
});

describe('CommunityMembersManager', () => {
  it('lists the members with their role, and offers controls only below the role of an admin (#1242)', async () => {
    renderManager('admin');

    const list = await screen.findByRole('list', { name: 'Members' });
    await within(list).findByText('Olivia');

    expect(within(list).getByRole('link', { name: 'Ursula' })).toHaveAttribute('href', '/community/tests/member/ursula');
    expect(within(list).queryByRole('button', { name: 'Remove Olivia' })).not.toBeInTheDocument();
    expect(within(list).queryByRole('button', { name: 'Remove Alan' })).not.toBeInTheDocument();
    expect(within(list).getByRole('button', { name: 'Remove Margaret' })).toBeInTheDocument();
    expect(within(list).getByRole('button', { name: 'Remove Ursula' })).toBeInTheDocument();
    expect(within(list).queryByRole('button', { name: /Role of Alan/ })).not.toBeInTheDocument();
    expect(within(list).getByRole('button', { name: /Role of Ursula/ })).toBeInTheDocument();
  });

  it('marks a member who hides from the member list, and no other member (#1466)', async () => {
    vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: page([...members, { ...member('q', 'Quinn', 'member'), in_member_list: false }]),
    });
    renderManager('admin');

    const list = await screen.findByRole('list', { name: 'Members' });
    await within(list).findByText('Quinn');

    const entryOf = (name: string) => within(list).getByRole('link', { name }).closest('li') as HTMLElement;
    expect(within(entryOf('Quinn')).getByText('Hidden from the list')).toBeInTheDocument();
    expect(within(entryOf('Ursula')).queryByText('Hidden from the list')).not.toBeInTheDocument();
    expect(within(list).getAllByText('Hidden from the list')).toHaveLength(1);
  });

  it('does not show the headline of a member: the second line stays the username (#1688)', async () => {
    renderManager('admin');

    const list = await screen.findByRole('list', { name: 'Members' });
    expect(await within(list).findByText('@ursula')).toBeInTheDocument();
    expect(screen.queryByText(/The headline of/)).not.toBeInTheDocument();
  });

  it('changes a role (#1242)', async () => {
    const update = vi
      .spyOn(knowiiApiClient.communities.members, 'updateRole')
      .mockResolvedValue({ ok: true, message: '', data: member('u', 'Ursula', 'moderator') });
    renderManager('owner');

    await userEvent.click(await screen.findByRole('button', { name: /Role of Ursula/ }));
    await userEvent.click(await screen.findByRole('option', { name: 'Moderator' }));

    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 'u', 'moderator'));
  });

  it('removes a member after a confirmation (#1242)', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.members, 'remove').mockResolvedValue({ ok: true, message: '', data: undefined });
    renderManager('owner');

    await userEvent.click(await screen.findByRole('button', { name: 'Remove Margaret' }));
    const dialog = await screen.findByRole('dialog', { name: 'Remove Margaret?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Remove' }));

    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'm'));
    await waitFor(() => expect(screen.queryByRole('link', { name: 'Margaret' })).not.toBeInTheDocument());
  });

  it('invites by email with a role lower than its own, and shows server field errors (#1242)', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.invitations, 'create')
      .mockResolvedValueOnce({
        ok: false,
        status: 400,
        message: 'Invalid',
        errors: { email: ['This person is already a member of the community'] },
      })
      .mockResolvedValueOnce({ ok: true, message: '', data: { ...invitation, email: 'ada@example.com', role: 'member' } });
    renderManager('moderator', allRights);

    await userEvent.type(await screen.findByLabelText('Invite by email'), 'ada@example.com');
    // A moderator only gives the member role
    await userEvent.click(screen.getByRole('button', { name: /^Role(?! of)/ }));
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Member']);
    await userEvent.keyboard('{Escape}');

    await userEvent.click(screen.getByRole('button', { name: 'Invite' }));
    expect(await screen.findByText('This person is already a member of the community')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Invite' }));
    await waitFor(() =>
      expect(create).toHaveBeenLastCalledWith('c1', { email: 'ada@example.com', role: 'member', access_groups: [], member_tags: [] }),
    );
    await waitFor(() => expect(screen.getByLabelText('Invite by email')).toHaveValue(''));
  });

  it('puts the focus back in the email field after an invitation is sent with the keyboard (#1618)', async () => {
    vi.spyOn(knowiiApiClient.communities.invitations, 'create').mockResolvedValue({
      ok: true,
      message: '',
      data: { ...invitation, email: 'ada@example.com', role: 'member' },
    });
    renderManager('owner');

    const email = await screen.findByLabelText('Invite by email');
    await userEvent.type(email, 'ada@example.com');
    const invite = screen.getByRole('button', { name: 'Invite' });
    act(() => invite.focus());
    await userEvent.keyboard('{Enter}');

    await waitFor(() => expect(email).toHaveValue(''));
    expect(email).toHaveFocus();
  });

  it('refuses an invalid email address before calling the API (#1242)', async () => {
    const create = vi.spyOn(knowiiApiClient.communities.invitations, 'create');
    renderManager('owner');

    await userEvent.type(await screen.findByLabelText('Invite by email'), 'not an email');
    await userEvent.click(screen.getByRole('button', { name: 'Invite' }));

    expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
    expect(create).not.toHaveBeenCalled();
  });

  it('lists the pending invitations and cancels the ones below its role (#1242)', async () => {
    const cancel = vi
      .spyOn(knowiiApiClient.communities.invitations, 'cancel')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    renderManager('admin');

    const list = await screen.findByRole('list', { name: 'Pending invitations' });
    expect(within(list).getByText('grace@example.com')).toBeInTheDocument();
    await userEvent.click(within(list).getByRole('button', { name: 'Cancel the invitation to grace@example.com' }));

    await waitFor(() => expect(cancel).toHaveBeenCalledWith('c1', 'i1'));
    await waitFor(() => expect(screen.getByText('No pending invitations.')).toBeInTheDocument());
  });

  it('shows the report of the mail provider on a pending invitation, in words (#1478)', async () => {
    vi.spyOn(knowiiApiClient.communities.invitations, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: page([
        { ...invitation, cuid: 'i2', email: 'hard@example.com', bounced_at: '2026-11-23T10:00:00.000000Z', bounce_type: 'hard' },
        { ...invitation, cuid: 'i3', email: 'soft@example.com', bounced_at: '2026-11-23T10:00:00.000000Z', bounce_type: 'soft' },
        { ...invitation, cuid: 'i4', email: 'fine@example.com' },
      ]),
    });
    renderManager('admin');

    const list = await screen.findByRole('list', { name: 'Pending invitations' });
    const rows = within(list).getAllByRole('listitem');
    expect(rows[0]).toHaveTextContent(INVITATION_WORDS.bounceHard);
    expect(rows[1]).toHaveTextContent(INVITATION_WORDS.bounceSoft);
    expect(rows[2]).not.toHaveTextContent(INVITATION_WORDS.bounceHard);
    expect(rows[2]).not.toHaveTextContent(INVITATION_WORDS.bounceSoft);
    expect(INVITATION_WORDS.bounceHard).toBe('This address could not be reached. Check it, then invite again.');
    expect(INVITATION_WORDS.bounceSoft).toBe('The email to this address did not arrive yet. Invite again later.');
  });

  it('shows no invitation form and no controls without the rights (#1242)', async () => {
    renderManager('moderator', { canAddCommunityMembers: false, canUpdateCommunityMembers: false, canRemoveCommunityMembers: false });

    await screen.findByRole('link', { name: 'Ursula' });
    expect(screen.queryByLabelText('Invite by email')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Remove/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('list', { name: 'Pending invitations' })).not.toBeInTheDocument();
  });
});

describe('removed members (#1486)', () => {
  const block: CommunityJoinBlock = { cuid: 'r1', name: 'Rita', username: 'rita', removed_at: new Date().toISOString() };

  it('lists the removed members, and lets a manager say "May join again"', async () => {
    vi.spyOn(knowiiApiClient.communities.members, 'joinBlocks').mockResolvedValue({ ok: true, message: '', data: [block] });
    const lift = vi
      .spyOn(knowiiApiClient.communities.members, 'liftJoinBlock')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    renderManager('admin');

    const list = await screen.findByRole('list', { name: 'Removed members' });
    expect(within(list).getByText('Rita')).toBeInTheDocument();
    expect(screen.getByText(/cannot join the community by themselves, even when it is open/)).toBeInTheDocument();

    screen.getByRole('button', { name: 'May join again: Rita' }).focus();
    await userEvent.keyboard('{Enter}');

    await waitFor(() => expect(lift).toHaveBeenCalledWith('c1', 'r1'));
    await waitFor(() => expect(screen.queryByRole('list', { name: 'Removed members' })).toBeNull());
  });

  it('shows nothing without a removed member', async () => {
    renderManager('admin');

    await screen.findByRole('link', { name: 'Ursula' });
    expect(screen.queryByText('Removed members')).toBeNull();
  });

  it('does not even ask for the list without the right to remove members', async () => {
    const list = vi.spyOn(knowiiApiClient.communities.members, 'joinBlocks');
    renderManager('moderator', { ...allRights, canRemoveCommunityMembers: false });

    await screen.findByRole('link', { name: 'Ursula' });
    expect(list).not.toHaveBeenCalled();
  });
});

describe('the member tags of the member list (#1389)', () => {
  const founder = {
    cuid: 't1',
    name: 'Founder',
    color: 'purple',
    visibility: 'members',
    members_count: null,
    created_at: '2026-11-22T10:00:00.000000Z',
  } as const;

  it('shows the tags of each member, and the button of their tags only with the right to assign them', async () => {
    vi.spyOn(knowiiApiClient.communities.members, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: page([member('o', 'Olivia', 'owner'), { ...member('u', 'Ursula', 'member'), tags: [founder] }]),
    });
    renderManager('owner', { ...allRights, canAssignMemberTags: false } as typeof allRights);

    const list = await screen.findByRole('list', { name: 'Members' });
    const ursula = (await within(list).findByRole('link', { name: 'Ursula' })).closest('li') as HTMLElement;
    expect(within(ursula).getByRole('list', { name: 'Tags' })).toHaveTextContent('Founder');
    expect(within(list).queryByRole('button', { name: 'Tags of Ursula' })).not.toBeInTheDocument();
  });

  it('opens the tags of one member, and the row follows a change', async () => {
    vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: [{ ...founder, members_count: 0 }],
    });
    vi.spyOn(knowiiApiClient.communities.memberTags.members, 'add').mockResolvedValue({
      ok: true,
      message: '',
      data: { cuid: 'u', name: 'Ursula', username: 'ursula', avatar_url: null },
    });
    const onMemberTagsChanged = vi.fn();
    render(
      <AppDataProvider>
        <CommunityMembersManager
          communityCuid="c1"
          communitySlug="tests"
          currentRole="owner"
          permissions={{ ...allRights, canAssignMemberTags: true }}
          onMemberTagsChanged={onMemberTagsChanged}
        />
      </AppDataProvider>,
    );

    const list = await screen.findByRole('list', { name: 'Members' });
    await userEvent.click(await within(list).findByRole('button', { name: 'Tags of Ursula' }));
    const dialog = await screen.findByRole('dialog', { name: 'Tags of Ursula' });
    await userEvent.click(await within(dialog).findByRole('checkbox', { name: /Founder/ }));

    const ursula = within(list).getByRole('link', { name: 'Ursula' }).closest('li') as HTMLElement;
    await waitFor(() => expect(within(ursula).getByRole('list', { name: 'Tags' })).toHaveTextContent('Founder'));
    expect(onMemberTagsChanged).toHaveBeenCalled();
  });
});

describe('CommunityMembersManager: the groups and the tags of an invitation (#1390)', () => {
  const tag = (cuid: string, name: string, visibility: MemberTag['visibility']): MemberTag => ({
    cuid,
    name,
    color: 'purple',
    visibility,
    members_count: 0,
    created_at: '2026-11-22T10:00:00.000000Z',
  });
  const group = (cuid: string, name: string): AccessGroup => ({ cuid, name, description: '', created_at: '2026-11-22T10:00:00.000000Z' });
  const grantRights = { ...allRights, canManageAccessGroupMembers: true, canAssignMemberTags: true };

  beforeEach(() => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [group('g1', 'Paid')] });
    vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: [tag('t1', 'Founder', 'members'), tag('t2', 'Onboarded', 'managers')],
    });
  });

  it('sends the chosen groups and tags with the invitation, then empties the picker', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.invitations, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { ...invitation, email: 'ada@example.com', role: 'member' } });
    render(
      <AppDataProvider>
        <CommunityMembersManager communityCuid="c1" communitySlug="tests" currentRole="owner" permissions={grantRights} />
      </AppDataProvider>,
    );

    await userEvent.type(await screen.findByLabelText('Invite by email'), 'ada@example.com');
    await userEvent.click(await screen.findByRole('button', { name: 'Access groups and tags' }));
    const dialog = await screen.findByRole('dialog', { name: 'Access groups and tags' });
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Paid' }));
    await userEvent.click(within(dialog).getByRole('checkbox', { name: /^Onboarded ?\(managers only\)$/ }));
    await userEvent.click(within(dialog).getByRole('button', { name: 'Done' }));
    // The dialog gives the focus back to its button after its leave transition: wait for it, so that the late return
    // never meets the focus that the send puts in the email field
    const pickerButton = await screen.findByRole('button', { name: 'Access groups and tags (2)' });
    await waitFor(() => expect(pickerButton).toHaveFocus());

    await userEvent.click(screen.getByRole('button', { name: 'Invite' }));

    await waitFor(() =>
      expect(create).toHaveBeenLastCalledWith('c1', {
        email: 'ada@example.com',
        role: 'member',
        access_groups: ['g1'],
        member_tags: ['t2'],
      }),
    );
    expect(await screen.findByRole('button', { name: 'Access groups and tags' })).toBeInTheDocument();
    await waitFor(() => expect(screen.getByLabelText('Invite by email')).toHaveFocus());
  });

  it('shows the groups of a pending invitation as text and its tags as badges', async () => {
    vi.spyOn(knowiiApiClient.communities.invitations, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: page([{ ...invitation, access_groups: [{ cuid: 'g1', name: 'Paid' }], member_tags: [tag('t2', 'Onboarded', 'managers')] }]),
    });
    render(
      <AppDataProvider>
        <CommunityMembersManager communityCuid="c1" communitySlug="tests" currentRole="owner" permissions={grantRights} />
      </AppDataProvider>,
    );

    const list = await screen.findByRole('list', { name: 'Pending invitations' });
    expect(await within(list).findByText('Access groups: Paid')).toBeInTheDocument();
    expect(within(within(list).getByRole('list', { name: 'Tags' })).getByText('Onboarded')).toBeInTheDocument();

    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations).toEqual([]);
  });

  it('loads 20 more pending invitations, says how many, and puts the focus on the first new row', async () => {
    // jsdom has no layout: the focus helper scrolls the row into view
    Element.prototype.scrollIntoView = vi.fn();
    const first = Array.from({ length: 20 }, (_, index) => ({ ...invitation, cuid: `i${index}`, email: `p${index}@example.com` }));
    const list = vi
      .spyOn(knowiiApiClient.communities.invitations, 'list')
      .mockResolvedValueOnce({ ok: true, message: '', data: page(first, 20) })
      .mockResolvedValueOnce({ ok: true, message: '', data: page([{ ...invitation, cuid: 'i20', email: 'p20@example.com' }]) });
    render(
      <AppDataProvider>
        <CommunityMembersManager communityCuid="c1" communitySlug="tests" currentRole="owner" permissions={allRights} />
      </AppDataProvider>,
    );

    await userEvent.click(await screen.findByRole('button', { name: 'Load more' }));
    await waitFor(() => expect(list).toHaveBeenLastCalledWith('c1', 20, 20));
    expect(await screen.findByText('1 more loaded')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText('p20@example.com').closest('li')).toHaveFocus());
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument();
  });

  it('shows no picker when the community has no group and no tag', async () => {
    vi.spyOn(knowiiApiClient.communities.accessGroups, 'list').mockResolvedValue({ ok: true, message: '', data: [] });
    vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({ ok: true, message: '', data: [] });
    render(
      <AppDataProvider>
        <CommunityMembersManager communityCuid="c1" communitySlug="tests" currentRole="owner" permissions={grantRights} />
      </AppDataProvider>,
    );

    expect(await screen.findByLabelText('Invite by email')).toBeInTheDocument();
    await act(async () => {
      await Promise.resolve();
    });
    expect(screen.queryByRole('button', { name: /Access groups and tags/ })).not.toBeInTheDocument();
  });
});
