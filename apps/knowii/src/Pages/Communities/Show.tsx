import AppLayout from '@/Layouts/AppLayout';
import { Community, CommunityPermissions, Permission, Role } from '@knowii/common';
import { Card } from 'primereact/card';
import classNames from 'classnames';
import { Divider } from 'primereact/divider';

interface Props {
  availablePermissions: string[];
  availableRoles: Role[];
  community: Community;
  defaultPermissions: Permission[];
  permissions: CommunityPermissions;
}

export default function CommunityPage(props: Props) {
  return (
    <AppLayout title={props.community.name} pageTitle={props.community.name}>
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
                <span className={classNames('text-[1.2rem] text-primary-500 leading-none')}>Recent Resources</span>
                {/* TODO only show button if user can add */}
                {/*<Button severity="success" className="p-1">*/}
                {/*  <FaPlus />*/}
                {/*</Button>*/}
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
                <span className={classNames('text-[1.2rem] text-primary-500 leading-none')}>Resource Collections</span>
                {/* TODO only show button if user can add */}
                {/*<Button severity="success" className="p-1">*/}
                {/*  <FaPlus />*/}
                {/*</Button>*/}
              </div>
            }
            subTitle={<Divider className={classNames('p-0 m-0')} />}
            className=""
          >
            <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>Coming soon...</p>
          </Card>
        </div>

        {/* Third Column */}
        <div className="md:w-1/5 md:h-full flex flex-col gap-6">
          {/* Upcoming events */}
          {/*<Card*/}
          {/*  title={*/}
          {/*    <div className="flex flex-row items-center justify-between">*/}
          {/*      <span className={classNames('text-[1.2rem] text-primary-500 leading-none')}>Upcoming Events</span>*/}
          {/*      /!* TODO only show button if user can add *!/*/}
          {/*      /!*<Button severity="success" className="p-1">*!/*/}
          {/*      /!*  <FaPlus />*!/*/}
          {/*      /!*</Button>*!/*/}
          {/*    </div>*/}
          {/*  }*/}
          {/*  subTitle={<Divider className={classNames('p-0 m-0')} />}*/}
          {/*  className=""*/}
          {/*>*/}
          {/*  <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>Coming soon...</p>*/}
          {/*</Card>*/}

          {/* Members */}
          <Card
            title={
              <div className="flex flex-row items-center justify-between">
                <span className={classNames('text-[1.2rem] text-primary-500 leading-none')}>Members</span>
                {/* TODO only show button if user can add */}
                {/*<Button severity="success" className="p-1">*/}
                {/*  <FaPlus />*/}
                {/*</Button>*/}
              </div>
            }
            subTitle={<Divider className={classNames('p-0 m-0')} />}
            className=""
          >
            <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6')}>Coming soon...</p>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
