import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { CommunityInvitation, CommunityMember, CommunityRole, Paginated } from '@knowii/common';
import { AppDataProvider, canActOnRole, grantableRoles, knowiiApiClient } from '@knowii/common';
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
  role,
  member_since: '2026-11-01T10:00:00.000000Z',
});

const members = [
  member('o', 'Olivia', 'owner'),
  member('a', 'Alan', 'admin'),
  member('m', 'Margaret', 'moderator'),
  member('u', 'Ursula', 'member'),
];

const page = (items: CommunityMember[]): Paginated<CommunityMember> => ({
  items,
  pagination: {
    limit: 20,
    offset: 0,
    previousOffset: null,
    nextOffset: null,
    currentPage: 1,
    pageCount: 1,
    totalCount: items.length,
  } as Paginated<CommunityMember>['pagination'],
});

const invitation: CommunityInvitation = {
  cuid: 'i1',
  email: 'grace@example.com',
  role: 'moderator',
  created_at: new Date().toISOString(),
  expires_at: new Date().toISOString(),
  is_expired: false,
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
  vi.spyOn(knowiiApiClient.communities.invitations, 'list').mockResolvedValue({ ok: true, message: '', data: [invitation] });
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
    await waitFor(() => expect(create).toHaveBeenLastCalledWith('c1', { email: 'ada@example.com', role: 'member' }));
    await waitFor(() => expect(screen.getByLabelText('Invite by email')).toHaveValue(''));
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

  it('shows no invitation form and no controls without the rights (#1242)', async () => {
    renderManager('moderator', { canAddCommunityMembers: false, canUpdateCommunityMembers: false, canRemoveCommunityMembers: false });

    await screen.findByRole('link', { name: 'Ursula' });
    expect(screen.queryByLabelText('Invite by email')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Remove/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('list', { name: 'Pending invitations' })).not.toBeInTheDocument();
  });
});
