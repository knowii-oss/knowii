import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityPermissions, CommunityRole, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, DASHBOARD_URL, useSocket } from '@knowii/common';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { AccessGroupsManager } from '@/Components/Communities/AccessGroupsManager';
import { router } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/Components/ui';
import { FaGear } from 'react-icons/fa6';
import DeleteCommunityForm from '@/Pages/Communities/Partials/DeleteCommunityForm';
import TransferOwnershipForm from '@/Pages/Communities/Partials/TransferOwnershipForm';
import { SpaceTreeEditor } from '@/Components/Spaces/SpaceTreeEditor';
import { DirectMessagesSetting } from '@/Components/Communities/DirectMessagesSetting';
import { ModeratorPermissionsForm } from '@/Components/Communities/ModeratorPermissionsForm';
import { CommunityMembersManager } from '@/Components/Communities/CommunityMembersManager';
import { UpdateCommunityForm } from '@/Components/Communities/UpdateCommunityForm';
import Separator from '@/Components/Separator';
import { useCallback, useState } from 'react';

interface Props {
  community: Community;
  spaceGroups: SpaceGroup[];
  permissions: CommunityPermissions;
  currentRole: CommunityRole;
}

export default function CommunitySettingsPage(props: Props) {
  useSocket({
    channel: {
      type: 'community',
      communityCuid: props.community.cuid,
    },
    event: 'community.deleted',
    callback: (_event, _deletedCommunity) => {
      // The current community has been deleted. Can't stay here anymore
      router.visit(route(DASHBOARD_URL), {
        preserveState: false,
      });
    },
  });

  const reloadSpaceTree = useCallback(() => {
    router.reload({ only: ['spaceGroups'] });
  }, []);

  useSocket({
    channel: {
      type: 'community',
      communityCuid: props.community.cuid,
    },
    event: 'community.space_tree.changed',
    callback: reloadSpaceTree,
  });

  // The members or the invitations changed (#1242): the member lists load again
  const [membersVersion, setMembersVersion] = useState(0);
  useSocket({
    channel: {
      type: 'community',
      communityCuid: props.community.cuid,
    },
    event: 'community.members.changed',
    callback: () => setMembersVersion((version) => version + 1),
  });

  const canManageMembers = props.permissions.canAddCommunityMembers;

  // The access groups changed (#1241): the list loads again
  const [accessGroupsVersion, setAccessGroupsVersion] = useState(0);
  useSocket({
    channel: {
      type: 'community',
      communityCuid: props.community.cuid,
    },
    event: 'community.access_groups.changed',
    callback: () => setAccessGroupsVersion((version) => version + 1),
  });
  const canSeeAccessGroups = props.permissions.canManageAccessGroups || props.permissions.canManageAccessGroupMembers;

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: props.community.name,
      href: route(COMMUNITY_URL, { communitySlug: props.community.slug }),
      icon: <CommunityIcon visibility={props.community.visibility} />,
    },
    { label: 'Settings', icon: <FaGear /> },
  ];

  return (
    <AppLayout
      contentWidth="form"
      browserPageTitle={`${props.community.name} - Settings`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      {props.permissions.canUpdateCommunity && (
        <>
          <UpdateCommunityForm community={props.community} onSaved={() => router.reload({ only: ['community'] })} />
          <Separator />
          <DirectMessagesSetting community={props.community} onSaved={() => router.reload({ only: ['community'] })} />
          <Separator />
        </>
      )}

      {props.permissions.canManageSpaces && (
        <SpaceTreeEditor communityCuid={props.community.cuid} spaceGroups={props.spaceGroups} onChanged={reloadSpaceTree} />
      )}

      {props.permissions.canManageModeratorPermissions && (
        <>
          {props.permissions.canManageSpaces && <Separator />}
          <ModeratorPermissionsForm community={props.community} />
        </>
      )}

      {canManageMembers && (
        <>
          {(props.permissions.canManageSpaces || props.permissions.canManageModeratorPermissions) && <Separator />}
          <CommunityMembersManager
            communityCuid={props.community.cuid}
            communitySlug={props.community.slug}
            currentRole={props.currentRole}
            permissions={props.permissions}
            refreshKey={membersVersion}
          />
        </>
      )}

      {canSeeAccessGroups && (
        <>
          {(props.permissions.canManageSpaces || props.permissions.canManageModeratorPermissions || canManageMembers) && <Separator />}
          <AccessGroupsManager communityCuid={props.community.cuid} permissions={props.permissions} refreshKey={accessGroupsVersion} />
        </>
      )}

      {props.permissions.canTransferOwnership && (
        <>
          <Separator />
          <TransferOwnershipForm
            communityCuid={props.community.cuid}
            communityName={props.community.name}
            refreshKey={membersVersion}
            onTransferred={() => router.reload()}
          />
        </>
      )}

      {(props.permissions.canManageSpaces || props.permissions.canManageModeratorPermissions || canManageMembers || canSeeAccessGroups) &&
        props.permissions.canDeleteCommunity && <Separator />}

      {props.permissions.canDeleteCommunity && (
        <DeleteCommunityForm communityCuid={props.community.cuid} communityName={props.community.name} />
      )}
    </AppLayout>
  );
}
