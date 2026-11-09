import { FaExternalLinkAlt, FaPlus } from 'react-icons/fa';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import type {
  Community,
  CommunityPermissions,
  CommunityResource,
  CommunityResourceCollection,
  Identifiable,
  SpaceGroup,
} from '@knowii/common';
import {
  COMMUNITY_SETTINGS_URL,
  DASHBOARD_URL,
  getIconForResourceLevel,
  RESOURCE_COLLECTION_URL,
  RESOURCE_URL,
  useSocket,
} from '@knowii/common';
import type { CreateResourceCollectionDialogSettings } from '../../Components/ResourceCollections/CreateResourceCollectionDialog';
import CommunityResourceCollectionDialog from '@/Components/ResourceCollections/CreateResourceCollectionDialog';
import CommunityResourceCollectionBox from '@/Components/ResourceCollections/CommunityResourceCollectionBox';
import CardGroup from '@/Components/CardGroup';
import { breadcrumbHome } from '../../Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import ResourceCollectionIcon from '@/Components/ResourceCollections/ResourceCollectionIcon';
import CommunityMembersIcon from '@/Components/Communities/CommunityMembersIcon';
import type { CreateResourceDialogSettings } from '../../Components/Resources/CreateResourceDialog';
import { CreateResourceDialog } from '../../Components/Resources/CreateResourceDialog';
import { Link, router } from '@inertiajs/react';
import { FaGear } from 'react-icons/fa6';
import { useImmer } from 'use-immer';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, buttonClasses, Card, CardBody, CardHeader } from '@/Components/ui';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollections: CommunityResourceCollection[];
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

  useSocket({
    channel: {
      type: 'community',
      communityCuid: props.community.cuid,
    },
    event: 'community.resource_collection.created',
    callback: (_event, newResourceCollection) => {
      handleResourceCollectionCreated(newResourceCollection);
    },
  });

  useSocket({
    channel: {
      type: 'community',
      communityCuid: props.community.cuid,
    },
    event: 'community.resource_collection.deleted',
    callback: (_event, deletedResourceCollection) => {
      handleResourceCollectionDeleted(deletedResourceCollection);
    },
  });

  const breadcrumbItems: BreadcrumbItem[] = [
    { label: props.community.name, icon: <CommunityIcon visibility={props.community.visibility} /> },
  ];

  const [resourceCollectionDialogSettings, setResourceCollectionDialogSettings] = useState<CreateResourceCollectionDialogSettings>({
    visible: false,
    communityCuid: '',
  });
  const [resourceCollections, setResourceCollections] = useImmer<CommunityResourceCollection[]>(props.resourceCollections);

  const openResourceCollectionDialog = (communityCuid: string) => {
    setResourceCollectionDialogSettings({
      visible: true,
      communityCuid,
    });
  };

  const closeResourceCollectionDialog = () => {
    setResourceCollectionDialogSettings({
      visible: false,
      communityCuid: '',
    });
  };

  const handleResourceCollectionCreated = (newResourceCollection: CommunityResourceCollection) => {
    setResourceCollections((draft) => {
      if (!draft.find((resourceCollection) => resourceCollection.cuid === newResourceCollection.cuid)) {
        draft.push(newResourceCollection);
        draft.sort((a, b) => a.name.localeCompare(b.name));

        setCreateResourceDialogSettings((prevSettings) => ({
          ...prevSettings,
          // Make sure the new resource collection is available for creating resources
          resourceCollections,
        }));
      }
    });
  };

  const handleResourceCollectionDeleted = (deletedResourceCollection: Identifiable) => {
    setResourceCollections((draft) => {
      const index = draft.findIndex((resourceCollection) => resourceCollection.cuid === deletedResourceCollection.cuid);
      if (index !== -1) {
        draft.splice(index, 1);
      }
    });
  };

  const [createResourceDialogSettings, setCreateResourceDialogSettings] = useState<CreateResourceDialogSettings>({
    visible: false,
    communityCuid: props.community.cuid,
    resourceCollections: props.resourceCollections ? props.resourceCollections : [],
  });

  const [recentResources, setRecentResources] = useState<CommunityResource[]>(props.recentResources ? props.recentResources : []);

  const openCreateResourceDialog = (communityCuid: string, resourceCollections: CommunityResourceCollection[]) => {
    setCreateResourceDialogSettings({
      visible: true,
      communityCuid,
      resourceCollections,
    });
  };

  const closeCreateResourceDialog = () => {
    setCreateResourceDialogSettings({
      visible: false,
      communityCuid: '',
      resourceCollections: [],
    });
  };

  const handleResourceCreated = (newResource: CommunityResource) => {
    setRecentResources([newResource, ...recentResources]);
  };

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
          <div className="flex flex-col gap-6 lg:w-2/5">
            {/* Recent resources */}
            <Card aria-labelledby="recent-resources-title" className="overflow-hidden">
              <CardHeader
                title={<span id="recent-resources-title">Recent Resources</span>}
                icon={<ResourceIcon />}
                actions={
                  // Can only create resources if allowed, and if there are resource collections in this community
                  props.permissions.canCreateResource &&
                  resourceCollections.length > 0 && (
                    <Button
                      size="sm"
                      iconOnly
                      aria-label="Add a resource"
                      onClick={() => openCreateResourceDialog(props.community.cuid, resourceCollections)}
                    >
                      <FaPlus aria-hidden="true" />
                    </Button>
                  )
                }
              />
              <CardBody>
                {recentResources.length > 0 ? (
                  <ul className="flex flex-col gap-3">
                    {recentResources.map((recentResource) => (
                      <li
                        key={recentResource.cuid}
                        className="flex items-start gap-2 rounded-lg border border-border bg-well p-3 transition-colors hover:border-overlay/40"
                      >
                        <Link
                          href={route(RESOURCE_URL, {
                            communitySlug: props.community.slug,
                            resourceCollectionSlug: recentResource.collection.slug,
                            resourceSlug: recentResource.slug,
                          })}
                          preserveState={true}
                          className="min-w-0 grow rounded"
                        >
                          <h3 className="text-base font-bold">
                            <span aria-hidden="true">{getIconForResourceLevel(recentResource.resource.level) + ' '}</span>
                            {recentResource.name ? recentResource.name : recentResource.resource.name}
                          </h3>
                          <p className="my-1 truncate text-sm text-muted">
                            {recentResource.description ? recentResource.description : recentResource.resource.description}
                          </p>
                        </Link>
                        {recentResource.resource.type === 'textArticle' && recentResource.resource.url && (
                          <a
                            href={recentResource.resource.url}
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Open the original article in a new tab"
                            className={buttonClasses({ variant: 'secondary', size: 'sm', iconOnly: true })}
                          >
                            <FaExternalLinkAlt aria-hidden="true" />
                          </a>
                        )}
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-sm text-muted">No resources yet.</p>
                )}
              </CardBody>
            </Card>
          </div>

          {/* Second Column */}
          <div className="flex flex-col gap-6 lg:w-2/5">
            {/* Resource collections */}
            <Card aria-labelledby="resource-collections-title" className="overflow-hidden">
              <CardHeader
                title={<span id="resource-collections-title">Resource Collections</span>}
                icon={<ResourceCollectionIcon />}
                actions={
                  props.permissions.canCreateResourceCollection && (
                    <Button
                      size="sm"
                      iconOnly
                      aria-label="Add a resource collection"
                      onClick={() => openResourceCollectionDialog(props.community.cuid)}
                    >
                      <FaPlus aria-hidden="true" />
                    </Button>
                  )
                }
              />
              <CardBody>
                {resourceCollections.length > 0 ? (
                  <CardGroup className="gap-3">
                    {resourceCollections.map((collection) => (
                      <li className="w-full md:w-auto" key={collection.cuid}>
                        <CommunityResourceCollectionBox
                          resourceCollection={collection}
                          link={route(RESOURCE_COLLECTION_URL, {
                            communitySlug: props.community.slug,
                            resourceCollectionSlug: collection.slug,
                          })}
                        />
                      </li>
                    ))}
                  </CardGroup>
                ) : (
                  <p className="text-sm text-muted">No resource collections yet.</p>
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

      <CommunityResourceCollectionDialog
        settings={resourceCollectionDialogSettings}
        onHide={closeResourceCollectionDialog}
        onResourceCollectionCreated={handleResourceCollectionCreated}
      />

      <CreateResourceDialog
        settings={createResourceDialogSettings}
        onHide={closeCreateResourceDialog}
        onResourceCreated={handleResourceCreated}
      />
    </AppLayout>
  );
}
