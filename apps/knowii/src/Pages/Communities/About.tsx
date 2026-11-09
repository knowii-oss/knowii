import { Link, router } from '@inertiajs/react';
import { FaCircleInfo, FaGear, FaUserShield, FaUsers } from 'react-icons/fa6';
import type { Community, CommunityMember, CommunityPermissions, CommunityRole, SpaceGroup } from '@knowii/common';
import {
  COMMUNITY_ROLE_LABELS,
  COMMUNITY_SETTINGS_URL,
  COMMUNITY_URL,
  COMMUNITY_VISIBILITY_DESCRIPTIONS,
  communityVisibilityOptions,
  DASHBOARD_URL,
  MEMBER_URL,
  MODERATOR_PERMISSION_LABELS,
  MODERATOR_PERMISSIONS,
  useSocket,
} from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { Avatar, buttonClasses, Card, CardBody, CardHeader, RelativeTime } from '@/Components/ui';

interface Props {
  community: Community;
  spaceGroups: SpaceGroup[];
  // The owner, the admins and the moderators
  team: CommunityMember[];
  permissions: CommunityPermissions;
  currentRole: CommunityRole;
}

/**
 * What the current role can do, in the words of the roles and permissions page (ADR 0017)
 */
function capabilities(props: Props): string[] {
  const { permissions, community, currentRole } = props;
  const items = ['Read the spaces open to members, post, comment and react'];
  if (permissions.canManageSpaces) {
    items.push('Create, edit, move and delete spaces');
  }
  if (permissions.canAddCommunityMembers) {
    items.push(currentRole === 'owner' ? 'Invite members and give any role' : 'Invite members and change the roles below your own');
  }
  const moderates = currentRole === 'owner' || currentRole === 'admin' || currentRole === 'moderator';
  const can = (right: 'pin_posts' | 'delete_content') =>
    currentRole === 'owner' || currentRole === 'admin' || community.moderator_permissions[right];
  if (moderates && can('pin_posts')) {
    items.push('Pin posts');
  }
  if (moderates && can('delete_content')) {
    items.push('Delete the posts and comments of other members');
  }
  if (permissions.canManageModeratorPermissions) {
    items.push('Choose what moderators can do');
  }
  if (permissions.canUpdateCommunity) {
    items.push('Change the details of the community');
  }
  if (permissions.canDeleteCommunity) {
    items.push('Delete the community');
  }
  return items;
}

/**
 * The details page of a community (#1135): what it is, its team, what moderators can do, and what the current member
 * can do, with the controls of their role.
 */
export default function CommunityAboutPage(props: Props) {
  const { community, team, permissions, currentRole } = props;

  useSocket({
    channel: { type: 'community', communityCuid: community.cuid },
    event: 'community.deleted',
    callback: () => router.visit(route(DASHBOARD_URL), { preserveState: false }),
  });

  // A role changed or a member joined: the team and the count load again
  useSocket({
    channel: { type: 'community', communityCuid: community.cuid },
    event: ['community.members.changed', 'community.updated'],
    callback: () => router.reload({ only: ['community', 'team', 'permissions', 'currentRole'] }),
  });

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: 'About', icon: <FaCircleInfo aria-hidden="true" /> },
  ];

  const visibility = communityVisibilityOptions.find((option) => option.visibility === community.visibility);
  const canOpenSettings = permissions.canManageCommunity || permissions.canManageSpaces || permissions.canAddCommunityMembers;

  return (
    <AppLayout
      browserPageTitle={`${community.name} - About`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
      pageActions={
        canOpenSettings ? (
          <Link
            href={route(COMMUNITY_SETTINGS_URL, { communitySlug: community.slug })}
            className={buttonClasses({ variant: 'ghost', size: 'sm' })}
          >
            <FaGear aria-hidden="true" />
            Settings
          </Link>
        ) : null
      }
    >
      <CommunityShell community={community} spaceGroups={props.spaceGroups}>
        <div className="flex flex-col gap-6">
          <Card as="section" aria-labelledby="about-title">
            <CardHeader title={<span id="about-title">About {community.name}</span>} icon={<FaCircleInfo aria-hidden="true" />} />
            <CardBody className="flex flex-col gap-4">
              <p className="whitespace-pre-line">{community.description || 'No description yet.'}</p>
              <dl className="grid gap-4 @md:grid-cols-2 @3xl:grid-cols-4">
                <div>
                  <dt className="text-sm text-muted">Visibility</dt>
                  <dd className="font-bold">
                    <span className={visibility?.color}>{visibility?.name ?? community.visibility}</span>
                    <span className="block text-sm font-normal text-muted">{COMMUNITY_VISIBILITY_DESCRIPTIONS[community.visibility]}</span>
                  </dd>
                </div>
                <div>
                  <dt className="text-sm text-muted">Members</dt>
                  <dd className="font-bold">{community.member_count ?? team.length}</dd>
                </div>
                <div>
                  <dt className="text-sm text-muted">Created</dt>
                  <dd className="font-bold">
                    <RelativeTime date={community.created_at} />
                  </dd>
                </div>
                <div>
                  <dt className="text-sm text-muted">Your role</dt>
                  <dd className="font-bold">{COMMUNITY_ROLE_LABELS[currentRole]}</dd>
                </div>
              </dl>
            </CardBody>
          </Card>

          <div className="grid gap-6 @3xl:grid-cols-2">
            <Card as="section" aria-labelledby="team-title">
              <CardHeader title={<span id="team-title">Team</span>} icon={<FaUsers aria-hidden="true" />} />
              <CardBody>
                <ul className="flex flex-col divide-y divide-border" aria-labelledby="team-title">
                  {team.map((member) => (
                    <li key={member.cuid} className="flex items-center gap-3 py-3">
                      <Avatar name={member.name} imageUrl={member.avatar_url} size="sm" />
                      <div className="flex min-w-0 flex-1 flex-col">
                        <Link
                          href={route(MEMBER_URL, { communitySlug: community.slug, username: member.username })}
                          className="truncate font-bold hover:underline"
                        >
                          {member.name}
                        </Link>
                        <span className="truncate text-sm text-muted">@{member.username}</span>
                      </div>
                      <span className="rounded-full bg-overlay/10 px-3 py-1 text-sm">{COMMUNITY_ROLE_LABELS[member.role]}</span>
                    </li>
                  ))}
                </ul>
              </CardBody>
            </Card>

            <div className="flex flex-col gap-6">
              <Card as="section" aria-labelledby="moderators-can-title">
                <CardHeader
                  title={<span id="moderators-can-title">What moderators can do</span>}
                  icon={<FaUserShield aria-hidden="true" />}
                />
                <CardBody>
                  <ul className="flex flex-col gap-2">
                    {MODERATOR_PERMISSIONS.map((right) => (
                      <li key={right} className="flex items-start justify-between gap-3">
                        <span>
                          <span className="font-bold">{MODERATOR_PERMISSION_LABELS[right].name}</span>
                          <span className="block text-sm text-muted">{MODERATOR_PERMISSION_LABELS[right].description}</span>
                        </span>
                        <span className={community.moderator_permissions[right] ? 'font-bold text-success' : 'text-muted'}>
                          {community.moderator_permissions[right] ? 'Yes' : 'No'}
                        </span>
                      </li>
                    ))}
                  </ul>
                  <p className="mt-3 text-xs text-muted">The owner and the admins choose these rights. They can always do all of it.</p>
                </CardBody>
              </Card>

              <Card as="section" aria-labelledby="you-can-title">
                <CardHeader title={<span id="you-can-title">What you can do</span>} />
                <CardBody>
                  <ul className="list-disc pl-5">
                    {capabilities(props).map((item) => (
                      <li key={item}>{item}</li>
                    ))}
                  </ul>
                  {canOpenSettings && (
                    <Link
                      href={route(COMMUNITY_SETTINGS_URL, { communitySlug: community.slug })}
                      className={buttonClasses({ variant: 'secondary', size: 'sm' }) + ' mt-4'}
                    >
                      <FaGear aria-hidden="true" />
                      Open the settings
                    </Link>
                  )}
                </CardBody>
              </Card>
            </div>
          </div>
        </div>
      </CommunityShell>
    </AppLayout>
  );
}
