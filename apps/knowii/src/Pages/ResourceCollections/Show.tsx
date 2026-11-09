import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityPermissions, CommunityResourceCollection } from '@knowii/common';
import { COMMUNITY_URL, useSocket, DASHBOARD_URL } from '@knowii/common';
import { router } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/Components/ui';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceCollectionIcon from '@/Components/ResourceCollections/ResourceCollectionIcon';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollection: CommunityResourceCollection;
}

export default function ResourceCollectionPage(props: Props) {
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

  useSocket({
    channel: {
      type: 'resourceCollection',
      communityCuid: props.community.cuid,
      resourceCollectionCuid: props.resourceCollection.cuid,
    },
    event: 'community.resource_collection.deleted',
    callback: (_event, _deletedResourceCollection) => {
      // The current resource collection has been deleted. Can't stay here anymore
      router.visit(route(COMMUNITY_URL, { communitySlug: props.community.slug }), {
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
    { label: props.resourceCollection.name, icon: <ResourceCollectionIcon /> },
  ];

  return (
    <AppLayout
      browserPageTitle={`${props.community.name} - ${props.resourceCollection.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      <p className="text-muted">Coming soon...</p>
    </AppLayout>
  );
}
