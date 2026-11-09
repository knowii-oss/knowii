import AppLayout from '@/Layouts/AppLayout';
import type { Community, CommunityPermissions, CommunityResource, SpaceGroup } from '@knowii/common';
import { COMMUNITY_SETTINGS_URL, DASHBOARD_URL, RESOURCE_URL, useSocket } from '@knowii/common';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import { ResourceCard } from '@/Components/Resources/ResourceCard';
import CommunityMembersIcon from '@/Components/Communities/CommunityMembersIcon';
import { Link, router } from '@inertiajs/react';
import { FaGear } from 'react-icons/fa6';
import type { BreadcrumbItem } from '@/Components/ui';
import { buttonClasses, Card, CardBody, CardHeader } from '@/Components/ui';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  // The newest resources of the resources spaces that the member can see (ADR 0015)
  recentResources: CommunityResource[];
  spaceGroups: SpaceGroup[];
}

export default function CommunityPage(props: Props) {
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
    { label: props.community.name, icon: <CommunityIcon visibility={props.community.visibility} /> },
  ];

  const recentResources = props.recentResources;

  return (
    <AppLayout
      browserPageTitle={props.community.name}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
      pageActions={
        props.permissions.canManageCommunity ? (
          <Link
            href={route(COMMUNITY_SETTINGS_URL, { communitySlug: props.community.slug })}
            preserveState={true}
            className={buttonClasses({ variant: 'ghost', size: 'sm' })}
          >
            <FaGear aria-hidden="true" />
            Settings
          </Link>
        ) : null
      }
    >
      <CommunityShell community={props.community} spaceGroups={props.spaceGroups}>
        <div className="flex flex-col gap-6 lg:flex-row">
          {/* First Column */}
          <div className="flex flex-col gap-6 lg:w-4/5">
            {/* Recent resources */}
            <Card aria-labelledby="recent-resources-title" className="overflow-hidden">
              <CardHeader title={<span id="recent-resources-title">Recent Resources</span>} icon={<ResourceIcon />} />
              <CardBody>
                {recentResources.length > 0 ? (
                  <ul className="flex flex-col gap-3">
                    {recentResources.map((recentResource) => (
                      <li key={recentResource.cuid}>
                        <ResourceCard
                          resource={recentResource}
                          href={route(RESOURCE_URL, {
                            communitySlug: props.community.slug,
                            spaceSlug: recentResource.space?.slug ?? '',
                            resourceSlug: recentResource.slug,
                          })}
                        />
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-sm text-muted">No resources yet.</p>
                )}
              </CardBody>
            </Card>
          </div>

          {/* Third Column */}
          <div className="flex flex-col gap-6 lg:h-full lg:w-1/5">
            {/* Members */}
            {'personal' !== props.community.visibility && (
              <Card aria-labelledby="members-title">
                <CardHeader title={<span id="members-title">Members</span>} icon={<CommunityMembersIcon />} />
                <CardBody>
                  <p className="text-sm text-muted">Coming soon...</p>
                </CardBody>
              </Card>
            )}
          </div>
        </div>
      </CommunityShell>
    </AppLayout>
  );
}
