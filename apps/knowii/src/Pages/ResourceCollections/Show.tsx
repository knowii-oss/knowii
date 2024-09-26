import AppLayout from '@/Layouts/AppLayout';
import { Community, CommunityPermissions, CommunityResourceCollection, COMMUNITY_URL } from '@knowii/common';
import { Link } from '@inertiajs/react';
import { MenuItem } from 'primereact/menuitem';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollection: CommunityResourceCollection;
}

export default function ResourceCollectionPage(props: Props) {
  const breadcrumbItems: MenuItem[] = [
    {
      label: props.community.name,
      template: (item) => <Link href={route(COMMUNITY_URL, { communitySlug: props.community.slug })}>{item.label}</Link>,
    },
    { label: props.resourceCollection.name },
  ];

  return (
    <AppLayout
      title={`${props.community.name} - ${props.resourceCollection.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome}
    >
      Coming soon...
    </AppLayout>
  );
}
