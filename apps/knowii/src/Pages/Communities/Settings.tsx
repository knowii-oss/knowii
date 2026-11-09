import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityPermissions } from '@knowii/common';
import { COMMUNITY_URL, DASHBOARD_URL, useSocket } from '@knowii/common';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { router } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/Components/ui';
import { FaGear } from 'react-icons/fa6';
import DeleteCommunityForm from '@/Pages/Communities/Partials/DeleteCommunityForm';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
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

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: props.community.name,
      href: route(COMMUNITY_URL, { communitySlug: props.community.slug }),
      icon: <CommunityIcon visibility={props.community.visibility} />,
    },
    { label: 'Settings', icon: <FaGear /> },
  ];

  return (
    <AppLayout browserPageTitle={`${props.community.name} - Settings`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      {props.permissions.canDeleteCommunity && (
        <DeleteCommunityForm communityCuid={props.community.cuid} communityName={props.community.name} />
      )}
    </AppLayout>
  );
}
