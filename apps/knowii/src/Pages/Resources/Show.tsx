import AppLayout from '@/Layouts/AppLayout';
import {
  Community,
  CommunityPermissions,
  CommunityResourceCollection,
  COMMUNITY_URL,
  CommunityResource,
  RESOURCE_COLLECTION_URL,
} from '@knowii/common';
import { Link } from '@inertiajs/react';
import { MenuItem } from 'primereact/menuitem';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
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
  const breadcrumbItems: MenuItem[] = [
    {
      label: props.community.name,
      template: (item) => (
        <Link href={route(COMMUNITY_URL, { communitySlug: props.community.slug })} preserveState={true}>
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
          preserveState={true}
        >
          <span className="flex items-center gap-2">
            <ResourceCollectionIcon />
            {item.label}
          </span>
        </Link>
      ),
    },
    {
      label: props.resource.name ? props.resource.name : props.resource.resource.name,
      template: (item) => (
        <span className="flex items-center gap-2">
          <ResourceIcon />
          {item.label}
        </span>
      ),
    },
  ];

  return (
    <AppLayout
      browserPageTitle={`${props.community.name} - ${props.resourceCollection.name} - Resource`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome}
    >
      Coming soon...
      {/*<h2>{props.resource.resource.name}</h2>*/}
      {/*{props.resource.resource_text_article?.markdown && (*/}
      {/*  <Markdown>{props.resource.resource_text_article.markdown}</Markdown>*/}
      {/*)}*/}
    </AppLayout>
  );
}
