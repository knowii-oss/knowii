import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityPermissions, CommunityResourceCollection, CommunityResource } from '@knowii/common';
import { COMMUNITY_URL, RESOURCE_COLLECTION_URL, useSocket, DASHBOARD_URL } from '@knowii/common';
import { router } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/Components/ui';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceCollectionIcon from '@/Components/ResourceCollections/ResourceCollectionIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollection: CommunityResourceCollection;
  resource: CommunityResource;
}

export default function ResourcePage(props: Props) {
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
    {
      label: props.resourceCollection.name,
      href: route(RESOURCE_COLLECTION_URL, {
        communitySlug: props.community.slug,
        resourceCollectionSlug: props.resourceCollection.slug,
      }),
      icon: <ResourceCollectionIcon />,
    },
    { label: props.resource.name ? props.resource.name : props.resource.resource.name, icon: <ResourceIcon /> },
  ];

  return (
    <AppLayout
      browserPageTitle={`${props.community.name} - ${props.resourceCollection.name} - Resource`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      <p className="text-muted">Coming soon...</p>
      {/*<h2>{props.resource.resource.name}</h2>*/}
      {/*{props.resource.resource_text_article?.markdown && (*/}
      {/*  <Markdown>{props.resource.resource_text_article.markdown}</Markdown>*/}
      {/*)}*/}
      {/*      {props.resource.resource.cover_image_base64 && (*/}
      {/*    <img*/}
      {/*      src={`data:image/jpeg;base64,${props.resource.resource.cover_image_base64}`}*/}
      {/*      alt="Resource Cover"*/}
      {/*      className="max-w-full h-auto rounded-lg shadow-md"*/}
      {/*    />*/}
      {/*)}*/}
    </AppLayout>
  );
}
