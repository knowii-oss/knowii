import AppLayout from '@/Layouts/AppLayout';
import { Community, CommunityPermissions, CommunityResourceCollection, RESOURCE_COLLECTION_URL } from '@knowii/common';
import { Card } from 'primereact/card';
import classNames from 'classnames';
import { Divider } from 'primereact/divider';
import { Button } from 'primereact/button';
import { FaPlus } from 'react-icons/fa';
import { useState } from 'react';
import CommunityResourceCollectionDialog from '@/Components/ResourceCollections/CreateResourceCollectionDialog';
import CommunityResourceCollectionBox from '@/Components/ResourceCollections/CommunityResourceCollectionBox';
import CardGroup from '@/Components/CardGroup';
import { MenuItem } from 'primereact/menuitem';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import ResourceCollectionIcon from '@/Components/ResourceCollections/ResourceCollectionIcon';
import CommunityMembersIcon from '@/Components/Communities/CommunityMembersIcon';

interface Props {
  community: Community;
  permissions: CommunityPermissions;
  resourceCollections: CommunityResourceCollection[];
}

export default function CommunityPage(props: Props) {
  const breadcrumbItems: MenuItem[] = [
    {
      label: props.community.name,
      template: (item) => (
        <span className="flex items-center gap-2">
          <CommunityIcon />
          {item.label}
        </span>
      ),
    },
  ];

  const [resourceCollectionDialogSettings, setResourceCollectionDialogSettings] = useState({
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
  };

  return (
    <AppLayout title={props.community.name} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome}>
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
                {props.permissions.canCreateResource && (
                  <Button severity="success" className="p-1">
                    <FaPlus />
                  </Button>
                )}
              </div>
            }
            subTitle={<Divider className={classNames('p-0 m-0')} />}
            className="p-0"
          >
            {/* TODO use card for each resource box */}
            {/*<Card*/}
            {/*  title={<span className={classNames('font-semibold text-base leading-none')}>Resource name</span>}*/}
            {/*  subTitle={<Divider className={classNames('p-0 m-0')} />}*/}
            {/*  className="p-0"*/}
            {/*>*/}
            {/*<div className="mb-2 p-2 rounded-lg border border-gray-200 hover:outline hover:outline-primary-500 hover:rounded-md shadow-lg overflow-hidden flex-shrink-0 hover:cursor-pointer">*/}
            {/*  <div className="flex justify-between items-center">*/}
            {/*    <h4 className="font-semibold text-base">Resource Name</h4>*/}
            {/*    <div className="flex flex-row justify-end items-center text-xs text-gray-500">*/}
            {/*      /!*<Button size="small" className="mr-2 p-[2px]" link={true}>*!/*/}
            {/*      /!*  <FaChevronUp />*!/*/}
            {/*      /!*</Button>*!/*/}
            {/*      /!*<Button size="small" className="mr-2 p-[2px]" link={true}>*!/*/}
            {/*      /!*  <FaChevronDown />*!/*/}
            {/*      /!*</Button>*!/*/}
            {/*      /!*<span className="mr-2 inline-flex items-center justify-center w-6 h-6 text-xs font-semibold text-gray-800 bg-gray-200 rounded-full">*!/*/}
            {/*      /!*  10*!/*/}
            {/*      /!*</span>*!/*/}
            {/*      /!*<Button*!/*/}
            {/*      /!*  link={true}*!/*/}
            {/*      /!*  className={false ? 'p-1 text-yellow-400 hover:text-gray-500' : 'p-1 text-gray-500 hover:text-yellow-400'}*!/*/}
            {/*      /!*>*!/*/}
            {/*      /!*  <FaStar />*!/*/}
            {/*      /!*</Button>*!/*/}
            {/*    </div>*/}
            {/*  </div>*/}
            {/*  <p className="text-gray-600 text-sm my-1 truncate">Resource description...</p>*/}
            {/*  <div className="flex justify-center sm:justify-end mt-2">*/}
            {/*    /!* add link around *!/*/}
            {/*    <Button severity="secondary" className="mr-2 py-[2px] px-2" size="small">*/}
            {/*      <FaExternalLinkAlt />*/}
            {/*    </Button>*/}
            {/*    /!* add link around *!/*/}
            {/*    <Button className="py-[2px] px-2" size="small">*/}
            {/*      Open*/}
            {/*    </Button>*/}
            {/*  </div>*/}
            {/*</div>*/}
            <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>Coming soon...</p>
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
            subTitle={<Divider className={classNames('p-0 m-0')} />}
            className=""
          >
            {resourceCollections.length > 0 ? (
              <CardGroup>
                {resourceCollections.map((collection) => (
                  <li className="w-full sm:w-auto bg-re" key={collection.cuid}>
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
              <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>
                No resource collections yet.
              </p>
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
              subTitle={<Divider className={classNames('p-0 m-0')} />}
              className=""
            >
              <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>Coming soon...</p>
            </Card>
          )}
        </div>
      </div>

      <CommunityResourceCollectionDialog
        settings={resourceCollectionDialogSettings}
        onHide={closeResourceCollectionDialog}
        onResourceCollectionCreated={handleResourceCollectionCreated}
      />
    </AppLayout>
  );
}
