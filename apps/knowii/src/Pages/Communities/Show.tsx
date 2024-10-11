import { Card } from 'primereact/card';
import { Divider } from 'primereact/divider';
import { Button } from 'primereact/button';
import { FaExternalLinkAlt, FaPlus } from 'react-icons/fa';
import { useState } from 'react';
import { MenuItem } from 'primereact/menuitem';
import AppLayout from '@/Layouts/AppLayout';
import {
  Community,
  CommunityPermissions,
  CommunityResource,
  CommunityResourceCollection,
  getIconForResourceLevel,
  RESOURCE_COLLECTION_URL,
  RESOURCE_URL,
} from '@knowii/common';
import CommunityResourceCollectionDialog, {
  CreateResourceCollectionDialogSettings,
} from '@/Components/ResourceCollections/CreateResourceCollectionDialog';
import CommunityResourceCollectionBox from '@/Components/ResourceCollections/CommunityResourceCollectionBox';
import CardGroup from '@/Components/CardGroup';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import ResourceCollectionIcon from '@/Components/ResourceCollections/ResourceCollectionIcon';
import CommunityMembersIcon from '@/Components/Communities/CommunityMembersIcon';
import { CreateResourceDialog, CreateResourceDialogSettings } from '@/Components/Resources/CreateResourceDialog';
import { router } from '@inertiajs/react';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollections: CommunityResourceCollection[];
  recentResources: CommunityResource[];
}

export default function CommunityPage(props: Props) {
  const breadcrumbItems: MenuItem[] = [
    {
      label: props.community.name,
      template: (item) => (
        <span className="flex items-center gap-2">
          <CommunityIcon visibility={props.community.visibility} />
          {item.label}
        </span>
      ),
    },
  ];

  const [resourceCollectionDialogSettings, setResourceCollectionDialogSettings] = useState<CreateResourceCollectionDialogSettings>({
    visible: false,
    communityCuid: '',
  });
  const [resourceCollections, setResourceCollections] = useState<CommunityResourceCollection[]>(props.resourceCollections);

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
    setResourceCollections((prevCollections) => [...prevCollections, newResourceCollection]);
    setCreateResourceDialogSettings((prevSettings) => ({
      ...prevSettings,
      // Make sure the new resource collection is available for creating resources
      resourceCollections,
    }));
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
    <AppLayout browserPageTitle={props.community.name} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome}>
      <div className="flex flex-col md:flex-row gap-6">
        {/* First Column */}
        <div className="md:w-2/5 flex flex-col gap-6">
          {/* Community news */}
          {/*<Card*/}
          {/*  title={<span className={classNames('text-[1.2rem] text-primary-500 leading-none')}>Community News</span>}*/}
          {/*  subTitle={<Divider className={classNames('p-0 m-0')} />}*/}
          {/*  className=""*/}
          {/*>*/}
          {/*  <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>Coming soon...</p>*/}
          {/*</Card>*/}

          {/* Recent resources */}
          <Card
            title={
              <div className="flex flex-row items-center justify-between">
                <span className="text-[1.2rem] text-primary-500 leading-none flex items-center gap-2">
                  <ResourceIcon />
                  Recent Resources
                </span>
                {
                  // Can only create resources if allowed, and if there are resource collections in this community
                  props.permissions.canCreateResource && resourceCollections.length > 0 && (
                    <Button
                      severity="success"
                      className="p-1"
                      onClick={() => openCreateResourceDialog(props.community.cuid, resourceCollections)}
                    >
                      <FaPlus />
                    </Button>
                  )
                }
              </div>
            }
            subTitle={<Divider className="p-0 m-0" />}
            className="p-0 overflow-hidden"
          >
            {recentResources.length > 0 ? (
              <div className="flex flex-col gap-5">
                {recentResources.map((recentResource) => (
                  <div
                    key={recentResource.cuid}
                    className="p-2 overflow-hidden rounded-md border border-gray-200 shadow-sm hover:shadow-md hover:border-primary-500 flex-shrink-0 cursor-pointer transition-all duration-200"
                  >
                    <div
                      className="flex justify-between items-center"
                      onClick={(e) => {
                        e.preventDefault();
                        const resourcePageLink = route(RESOURCE_URL, {
                          communitySlug: props.community.slug,
                          resourceCollectionSlug: recentResource.collection.slug,
                          resourceSlug: recentResource.slug,
                        });
                        router.visit(resourcePageLink, {
                          preserveState: true,
                        });
                      }}
                    >
                      <h4 className="font-semibold text-base">
                        {getIconForResourceLevel(recentResource.resource.level) + ' '}
                        {recentResource.name ? recentResource.name : recentResource.resource.name}
                      </h4>
                      <div className="flex flex-row justify-end items-center text-xs text-gray-500">
                        {/*<Button size="small" className="mr-2 p-[2px]" link={true}>*/}
                        {/*  <FaChevronUp />*/}
                        {/*</Button>*/}
                        {/*<Button size="small" className="mr-2 p-[2px]" link={true}>*/}
                        {/*  <FaChevronDown />*/}
                        {/*</Button>*/}
                        {/*<span className="mr-2 inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-gray-800 bg-gray-200 rounded-full">*/}
                        {/*  10*/}
                        {/*</span>*/}
                        {/*<Button*/}
                        {/*  link={true}*/}
                        {/*  className={false ? 'p-1 text-yellow-400 hover:text-gray-500' : 'p-1 text-gray-500 hover:text-yellow-400'}*/}
                        {/*>*/}
                        {/*  <FaStar />*/}
                        {/*</Button>*/}
                      </div>
                    </div>
                    <p
                      className="text-gray-600 text-sm my-1 truncate"
                      onClick={(e) => {
                        e.preventDefault();
                        const resourcePageLink = route(RESOURCE_URL, {
                          communitySlug: props.community.slug,
                          resourceCollectionSlug: recentResource.collection.slug,
                          resourceSlug: recentResource.slug,
                        });
                        router.visit(resourcePageLink, {
                          preserveState: true,
                        });
                      }}
                    >
                      {recentResource.description ? recentResource.description : recentResource.resource.description}
                    </p>
                    <div className="flex justify-end mt-2 gap-2">
                      <div
                        className="w-full"
                        onClick={(e) => {
                          e.preventDefault();
                          const resourcePageLink = route(RESOURCE_URL, {
                            communitySlug: props.community.slug,
                            resourceCollectionSlug: recentResource.collection.slug,
                            resourceSlug: recentResource.slug,
                          });
                          router.visit(resourcePageLink, {
                            preserveState: true,
                          });
                        }}
                      ></div>
                      {recentResource.resource.type === 'textArticle' && recentResource.resource.url && (
                        <a href={recentResource.resource.url} target="_blank" rel="noreferrer">
                          <Button severity="secondary" size="small" className="py-[6px] px-2">
                            <FaExternalLinkAlt />
                          </Button>
                        </a>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6">No resources yet.</p>
            )}
          </Card>
        </div>

        {/* Second Column */}
        <div className="md:w-2/5 flex flex-col gap-6">
          {/* Resource collections */}
          <Card
            title={
              <div className="flex flex-row items-center justify-between">
                <span className="text-[1.2rem] text-primary-500 leading-none flex items-center gap-2">
                  <ResourceCollectionIcon />
                  Resource Collections
                </span>
                {props.permissions.canCreateResourceCollection && (
                  <Button severity="success" className="p-1" onClick={() => openResourceCollectionDialog(props.community.cuid)}>
                    <FaPlus />
                  </Button>
                )}
              </div>
            }
            subTitle={<Divider className="p-0 m-0" />}
            className="overflow-hidden"
          >
            {resourceCollections.length > 0 ? (
              <CardGroup>
                {resourceCollections.map((collection) => (
                  <li className="w-full md:w-auto" key={collection.cuid}>
                    <CommunityResourceCollectionBox
                      key={collection.cuid}
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
              <p className="text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6">No resource collections yet.</p>
            )}
          </Card>
        </div>

        {/* Third Column */}
        <div className="md:w-1/5 md:h-full flex flex-col gap-6">
          {/* Members */}
          {'personal' !== props.community.visibility && (
            <Card
              title={
                <div className="flex flex-row items-center justify-between">
                  <span className="text-[1.2rem] text-primary-500 leading-none flex items-center gap-2">
                    <CommunityMembersIcon />
                    Members
                  </span>
                </div>
              }
              subTitle={<Divider className="p-0 m-0" />}
              className=""
            >
              <p className="text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6">Coming soon...</p>
            </Card>
          )}
        </div>
      </div>

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
