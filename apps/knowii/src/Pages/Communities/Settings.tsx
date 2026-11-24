import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityPermissions, CommunityRole, Space, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, DASHBOARD_URL, SPACE_URL, useSocket, useTypedPage } from '@knowii/common';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { AccessGroupsManager } from '@/Components/Communities/AccessGroupsManager';
import { router } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/Components/ui';
import { Divider } from '@/Components/ui';
import { FaGear } from 'react-icons/fa6';
import DeleteCommunityForm from '@/Pages/Communities/Partials/DeleteCommunityForm';
import TransferOwnershipForm from '@/Pages/Communities/Partials/TransferOwnershipForm';
import { SpaceTreeEditor } from '@/Components/Spaces/SpaceTreeEditor';
import { DirectMessagesSetting } from '@/Components/Communities/DirectMessagesSetting';
import { IndexingSetting } from '@/Components/Communities/IndexingSetting';
import { JoinPolicySetting } from '@/Components/Communities/JoinPolicySetting';
import { ModeratorPermissionsForm } from '@/Components/Communities/ModeratorPermissionsForm';
import { CommunityMembersManager } from '@/Components/Communities/CommunityMembersManager';
import { InviteLinksManager } from '@/Components/Communities/InviteLinksManager';
import { MemberTagsManager } from '@/Components/Communities/MemberTagsManager';
import { UpdateCommunityForm } from '@/Components/Communities/UpdateCommunityForm';
import { useCallback, useState } from 'react';

interface Props {
  community: Community;
  spaceGroups: SpaceGroup[];
  permissions: CommunityPermissions;
  currentRole: CommunityRole;
}

export default function CommunitySettingsPage(props: Props) {
  // The account of the page: the transfer of the ownership asks for the code of an owner with two-factor authentication
  const page = useTypedPage();
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

  // The toast after a new space opens it (#1576)
  const spaceHref = (space: Space) => route(SPACE_URL, { communitySlug: props.community.slug, spaceSlug: space.slug });

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
  const canManageInviteLinks = props.permissions.canManageInviteLinks === true && props.community.visibility !== 'personal';

  // Member tags (#1389): no event tells the page, so each section tells the other one. A tag put on a member changes
  // the counts of the tags; a renamed or deleted tag changes the rows of the members
  const [memberTagsVersion, setMemberTagsVersion] = useState(0);
  const [membersTagsVersion, setMembersTagsVersion] = useState(0);
  const canSeeMemberTags = props.permissions.canViewMemberTags;

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
          <Divider className="my-10" />
          <DirectMessagesSetting community={props.community} onSaved={() => router.reload({ only: ['community'] })} />
          <Divider className="my-10" />
        </>
      )}

      {/* Owner and admins: who can join (#1486). A personal community has nobody to let in */}
      {props.permissions.canManageJoinPolicy === true && props.community.visibility !== 'personal' && (
        <>
          <JoinPolicySetting community={props.community} onSaved={() => router.reload({ only: ['community'] })} />
          <Divider className="my-10" />
        </>
      )}

      {/* Owner and admins: search engines and the public spaces (#1487). A personal community has no public space */}
      {props.permissions.canManageIndexing === true && props.community.visibility !== 'personal' && (
        <>
          <IndexingSetting community={props.community} onSaved={() => router.reload({ only: ['community'] })} />
          <Divider className="my-10" />
        </>
      )}

      {props.permissions.canManageSpaces && (
        <SpaceTreeEditor
          communityCuid={props.community.cuid}
          spaceGroups={props.spaceGroups}
          onChanged={reloadSpaceTree}
          spaceHref={spaceHref}
          personalCommunity={props.community.visibility === 'personal'}
        />
      )}

      {props.permissions.canManageModeratorPermissions && (
        <>
          {props.permissions.canManageSpaces && <Divider className="my-10" />}
          <ModeratorPermissionsForm community={props.community} />
        </>
      )}

      {canManageMembers && (
        <>
          {(props.permissions.canManageSpaces || props.permissions.canManageModeratorPermissions) && <Divider className="my-10" />}
          <CommunityMembersManager
            communityCuid={props.community.cuid}
            communitySlug={props.community.slug}
            currentRole={props.currentRole}
            permissions={props.permissions}
            refreshKey={membersVersion + membersTagsVersion}
            grantsRefreshKey={accessGroupsVersion + membersTagsVersion}
            onMemberTagsChanged={() => setMemberTagsVersion((version) => version + 1)}
          />
        </>
      )}

      {/* Invite links (#1391, ADR 0062): the owner and the admins of a shared community, after the members */}
      {canManageInviteLinks && (
        <>
          {(props.permissions.canManageSpaces || props.permissions.canManageModeratorPermissions || canManageMembers) && (
            <Divider className="my-10" />
          )}
          <InviteLinksManager
            communityCuid={props.community.cuid}
            permissions={props.permissions}
            refreshKey={accessGroupsVersion + memberTagsVersion + membersTagsVersion}
          />
        </>
      )}

      {canSeeMemberTags && (
        <>
          {(props.permissions.canManageSpaces ||
            props.permissions.canManageModeratorPermissions ||
            canManageMembers ||
            canManageInviteLinks) && <Divider className="my-10" />}
          <MemberTagsManager
            communityCuid={props.community.cuid}
            permissions={props.permissions}
            refreshKey={memberTagsVersion}
            onChanged={() => setMembersTagsVersion((version) => version + 1)}
          />
        </>
      )}

      {canSeeAccessGroups && (
        <>
          {(props.permissions.canManageSpaces ||
            props.permissions.canManageModeratorPermissions ||
            canManageMembers ||
            canSeeMemberTags) && <Divider className="my-10" />}
          <AccessGroupsManager communityCuid={props.community.cuid} permissions={props.permissions} refreshKey={accessGroupsVersion} />
        </>
      )}

      {props.permissions.canTransferOwnership && (
        <>
          <Divider className="my-10" />
          <TransferOwnershipForm
            communityCuid={props.community.cuid}
            communityName={props.community.name}
            refreshKey={membersVersion}
            onTransferred={() => router.reload()}
            twoFactor={page.props.auth.user?.two_factor_enabled === true}
          />
        </>
      )}

      {(props.permissions.canManageSpaces ||
        props.permissions.canManageModeratorPermissions ||
        canManageMembers ||
        canSeeMemberTags ||
        canSeeAccessGroups) &&
        props.permissions.canDeleteCommunity && <Divider className="my-10" />}

      {props.permissions.canDeleteCommunity && (
        <DeleteCommunityForm communityCuid={props.community.cuid} communityName={props.community.name} />
      )}
    </AppLayout>
  );
}
