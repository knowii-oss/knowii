import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import type { Community, CommunityMember, CommunityPermissions, CommunityRole } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import CommunityAboutPage from './About';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { visit: vi.fn(), reload: vi.fn() },
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children, pageActions }: { children: React.ReactNode; pageActions?: React.ReactNode }) => (
    <div>
      <div data-testid="page-actions">{pageActions}</div>
      {children}
    </div>
  ),
}));

vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
    writable: true,
  });
  Object.defineProperty(window, 'Echo', {
    value: { private: () => ({ listen: () => undefined, stopListening: () => undefined }), leaveChannel: () => undefined },
    writable: true,
  });
});

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: 'A place to learn',
  visibility: 'private',
  moderator_permissions: { manage_spaces: false, manage_members: true, pin_posts: true, delete_content: false },
  member_count: 12,
  created_at: new Date('2026-11-01T10:00:00Z'),
  updated_at: new Date('2026-11-01T10:00:00Z'),
} as Community;

const team: CommunityMember[] = [
  { cuid: 'o', name: 'Olivia', username: 'olivia', role: 'owner', member_since: null },
  { cuid: 'm', name: 'Margaret', username: 'margaret', role: 'moderator', member_since: null },
];

const none: CommunityPermissions = {
  canManageCommunity: false,
  canManageSpaces: false,
  canManageModeratorPermissions: false,
  canUpdateCommunity: false,
  canDeleteCommunity: false,
  canAddCommunityMembers: false,
  canUpdateCommunityMembers: false,
  canRemoveCommunityMembers: false,
};

function renderPage(currentRole: CommunityRole, permissions: Partial<CommunityPermissions> = {}) {
  render(
    <AppDataProvider>
      <CommunityAboutPage
        community={community}
        spaceGroups={[]}
        team={team}
        permissions={{ ...none, ...permissions }}
        currentRole={currentRole}
      />
    </AppDataProvider>,
  );
}

describe('CommunityAboutPage', () => {
  it('shows the details, the team and what moderators can do (#1135)', () => {
    renderPage('member');

    expect(screen.getByText('A place to learn')).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
    expect(screen.getByText('Members only. People join with an invitation.')).toBeInTheDocument();
    const teamList = screen.getByRole('list', { name: 'Team' });
    expect(within(teamList).getByRole('link', { name: 'Olivia' })).toHaveAttribute('href', '/members.show/tests/olivia');
    expect(within(teamList).getByText('Moderator')).toBeInTheDocument();
    const rights = screen.getByRole('region', { name: 'What moderators can do' });
    expect(within(rights).getAllByText('Yes')).toHaveLength(2);
    expect(within(rights).getAllByText('No')).toHaveLength(2);
  });

  it('gives a member no settings control (#1135)', () => {
    renderPage('member');

    expect(screen.queryByRole('link', { name: /Settings/ })).not.toBeInTheDocument();
    const you = screen.getByRole('region', { name: 'What you can do' });
    expect(within(you).getAllByRole('listitem')).toHaveLength(1);
  });

  it('lists the rights of a moderator and links to the settings when they manage members (#1135)', () => {
    renderPage('moderator', { canAddCommunityMembers: true, canUpdateCommunityMembers: true, canRemoveCommunityMembers: true });

    const you = screen.getByRole('region', { name: 'What you can do' });
    expect(within(you).getByText('Invite members and change the roles below your own')).toBeInTheDocument();
    expect(within(you).getByText('Pin posts')).toBeInTheDocument();
    expect(within(you).queryByText('Delete the posts and comments of other members')).not.toBeInTheDocument();
    expect(within(you).getByRole('link', { name: 'Open the settings' })).toHaveAttribute('href', '/communities.settings/tests');
    expect(within(screen.getByTestId('page-actions')).getByRole('link', { name: 'Settings' })).toBeInTheDocument();
  });

  it('lists every control of the owner (#1135)', () => {
    renderPage('owner', {
      canManageCommunity: true,
      canManageSpaces: true,
      canManageModeratorPermissions: true,
      canUpdateCommunity: true,
      canDeleteCommunity: true,
      canAddCommunityMembers: true,
    });

    const you = screen.getByRole('region', { name: 'What you can do' });
    expect(within(you).getByText('Invite members and give any role')).toBeInTheDocument();
    expect(within(you).getByText('Delete the posts and comments of other members')).toBeInTheDocument();
    expect(within(you).getByText('Delete the community')).toBeInTheDocument();
  });
});
