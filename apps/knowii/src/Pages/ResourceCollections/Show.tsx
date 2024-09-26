import AppLayout from '@/Layouts/AppLayout';
import { Community, CommunityPermissions, CommunityResourceCollection, COMMUNITY_URL } from '@knowii/common';
import { Link } from '@inertiajs/react';
import { MenuItem } from 'primereact/menuitem';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import { RESOURCE_COLLECTION_URL } from '@knowii/common';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceCollectionIcon from '@/Components/ResourceCollections/ResourceCollectionIcon';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollection: CommunityResourceCollection;
}

export default function ResourceCollectionPage(props: Props) {
  const breadcrumbItems: MenuItem[] = [
    {
      label: props.community.name,
      template: (item) => (
        <Link href={route(COMMUNITY_URL, { communitySlug: props.community.slug })}>
          <span className="flex items-center gap-2">
            <CommunityIcon />
            {item.label}
          </span>
        </Link>
      ),
    },
    {
      label: props.resourceCollection.name,
      template: (item) => (
        <Link
          href={route(RESOURCE_COLLECTION_URL, {
            communitySlug: props.community.slug,
            resourceCollectionSlug: props.resourceCollection.slug,
          })}
        >
          <span className="flex items-center gap-2">
            <ResourceCollectionIcon />
            {item.label}
          </span>
        </Link>
      ),
    },
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
