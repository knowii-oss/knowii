import { MenuItem } from 'primereact/menuitem';
import AppLayout from '@/Layouts/AppLayout';
import { Community, COMMUNITY_URL, CommunityPermissions } from '@knowii/common';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { Link } from '@inertiajs/react';
import { FaGear } from 'react-icons/fa6';
import DeleteCommunityForm from '@/Pages/Communities/Partials/DeleteCommunityForm';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
}

export default function CommunitySettingsPage(props: Props) {
  const breadcrumbItems: MenuItem[] = [
    {
      label: props.community.name,
      template: (item) => (
        <Link href={route(COMMUNITY_URL, { communitySlug: props.community.slug })} preserveState={true}>
          <span className="flex items-center gap-2">
            <CommunityIcon visibility={props.community.visibility} />
            {item.label}
          </span>
        </Link>
      ),
    },
    {
      label: 'Settings',
      template: (item) => (
        <span className="flex items-center gap-2">
          <FaGear />
          {item.label}
        </span>
      ),
    },
  ];

  return (
    <AppLayout browserPageTitle={`${props.community.name} - Settings`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome}>
      {props.permissions.canDeleteCommunity && (
        <DeleteCommunityForm communityCuid={props.community.cuid} communityName={props.community.name} />
      )}
    </AppLayout>
  );
}
