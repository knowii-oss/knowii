import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityResource, Space, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, RESOURCE_URL, SPACE_TYPE_LABELS, SPACE_URL } from '@knowii/common';
import type { BreadcrumbItem } from '@/Components/ui';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import { ResourceDetails } from '@/Components/Resources/ResourceDetails';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';

interface Props {
  // WARNING: aligned with CommunityResourceController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  resource: CommunityResource;
}

/**
 * A resource of a resources space (ADR 0015, #1133).
 */
export default function ResourcePage(props: Props) {
  const { community, space, resource } = props;
  const name = resource.name || resource.resource.name;

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    {
      label: space.name,
      href: route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug }),
      icon: <span aria-hidden="true">{space.emoji || SPACE_TYPE_LABELS[space.type].emoji}</span>,
    },
    { label: name, icon: <ResourceIcon /> },
  ];

  return (
    <AppLayout
      browserPageTitle={`${name} - ${space.name} - ${community.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
    >
      <CommunityShell community={community} spaceGroups={props.spaceGroups} activeSpaceCuid={space.cuid}>
        <ResourceDetails
          communitySlug={community.slug}
          resource={resource}
          shareUrl={route(RESOURCE_URL, { communitySlug: community.slug, spaceSlug: space.slug, resourceSlug: resource.slug })}
        />
      </CommunityShell>
    </AppLayout>
  );
}
