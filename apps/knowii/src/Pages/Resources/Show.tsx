import AppLayout from '@/Layouts/AppLayout';
import type { Comment, Community, CommunityResource, Space, SpaceGroup } from '@knowii/common';
import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  COMMUNITY_URL,
  knowiiApiClient,
  RESOURCE_URL,
  SPACE_TYPE_LABELS,
  SPACE_URL,
  useAppData,
  useSocket,
  useTypedPage,
} from '@knowii/common';
import type { BreadcrumbItem } from '@/Components/ui';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import { ResourceChangesForm } from '@/Components/Resources/ResourceChangesForm';
import { ResourceDetails } from '@/Components/Resources/ResourceDetails';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import { CommentSection } from '@/Components/Comments/CommentSection';

interface Props {
  // WARNING: aligned with CommunityResourceController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  resource: CommunityResource;
  // Comments on the resource (#1034)
  comments: Comment[];
  permissions: { canComment: boolean; canModerate: boolean; canCapture: boolean; canUpdate: boolean };
}

/**
 * A resource of a resources space (ADR 0015, #1133).
 */
export default function ResourcePage(props: Props) {
  const { community, space, resource } = props;
  const name = resource.name || resource.resource.name;
  const page = useTypedPage();
  const { showToast } = useAppData();
  const [captureStarting, setCaptureStarting] = useState(false);
  const spaceChannel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );

  // The capture job saves the resource at each step (ADR 0018): the page loads it again
  useSocket({
    channel: spaceChannel,
    event: ['community.resource.updated', 'community.resource.deleted'],
    callback: (event, payload) => {
      if (payload.cuid !== resource.cuid) {
        return;
      }
      if (event === 'community.resource.deleted') {
        router.visit(route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug }));
        return;
      }
      router.reload({ only: ['resource'] });
    },
  });

  const captureAgain = async () => {
    setCaptureStarting(true);
    const result = await knowiiApiClient.resources.capture(community.cuid, space.cuid, resource.cuid);
    setCaptureStarting(false);
    if (result.ok) {
      router.reload({ only: ['resource'] });
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

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
      <CommunityShell community={community} spaceGroups={props.spaceGroups} activeSpaceCuid={space.cuid} contentWidth="readable">
        <ResourceDetails
          communitySlug={community.slug}
          resource={resource}
          shareUrl={route(RESOURCE_URL, { communitySlug: community.slug, spaceSlug: space.slug, resourceSlug: resource.slug })}
          onCaptureAgain={props.permissions.canCapture ? captureAgain : undefined}
          captureStarting={captureStarting}
        />

        {props.permissions.canUpdate && (
          <ResourceChangesForm
            // A new type from the capture or from another member resets the form
            key={`${resource.type}-${resource.level}`}
            communityCuid={community.cuid}
            spaceCuid={space.cuid}
            resource={resource}
            onSaved={() => router.reload({ only: ['resource'] })}
          />
        )}

        <CommentSection
          subject={{ communityCuid: community.cuid, spaceCuid: space.cuid, resourceCuid: resource.cuid }}
          initialComments={props.comments}
          currentUserCuid={page.props.auth.user?.cuid ?? null}
          canComment={props.permissions.canComment}
          canModerate={props.permissions.canModerate}
        />
      </CommunityShell>
    </AppLayout>
  );
}
