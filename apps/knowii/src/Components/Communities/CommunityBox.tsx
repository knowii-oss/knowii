import { Divider } from 'primereact/divider';
import { Card } from 'primereact/card';
import { Community, communityVisibilityOptions } from '@knowii/common';
import { FaPlus } from 'react-icons/fa';
import { Link } from '@inertiajs/react';
import CommunityIcon from './CommunityIcon';

interface Props {
  community?: Community;
  creationMode: boolean;
  clickHandler?: () => void;
  /**
   * Optional link to navigate to when the community box is clicked.
   * Link is only added if creationMode is false.
   */
  link?: string;
}

export default function CommunityBox(props: Props) {
  const visibilityLabel = props.community?.visibility
    ? communityVisibilityOptions.find((v) => v.visibility === props.community?.visibility)?.name
    : '';

  const normalModeCard = props.community && (
    <Card
      title={
        <span className="text-[1.2rem] text-primary-500 leading-none flex items-center gap-2">
          <CommunityIcon visibility={props.community.visibility} />
          {props.community.name}
        </span>
      }
      subTitle={<Divider className="p-0 m-0" />}
      className="h-[250px] relative"
      onClick={
        props.clickHandler
          ? props.clickHandler
          : // eslint-disable-next-line @typescript-eslint/no-empty-function
            () => {}
      }
    >
      <p className="text-sm text-gray-800 text-ellipsis line-clamp-4 md:line-clamp-5 min-h-6">{props.community.description}</p>
      <span className="absolute bottom-2 right-4 text-xs text-gray-600 font-bold">{visibilityLabel}</span>
    </Card>
  );

  return props.creationMode ? (
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    <div
      className="w-full sm:w-[450px] h-[250px] rounded-md flex flex-col justify-center items-center outline-dashed outline-2 outline-offset-[-2px] text-gray-800 cursor-pointer hover:text-primary-500 hover:outline-4 hover:outline-primary-500 hover:outline-offset-[0px] hover:rounded-md"
      onClick={
        props.clickHandler
          ? props.clickHandler
          : // eslint-disable-next-line @typescript-eslint/no-empty-function
            () => {}
      }
    >
      <FaPlus className="text-3xl" />
      <span className="text-xl font-bold">New</span>
    </div>
  ) : (
    <div className="w-full sm:w-[450px] h-[250px] rounded-lg hover:outline hover:outline-primary-500 hover:outline-offset-2 hover:rounded-md shadow-lg overflow-hidden flex-shrink-0">
      {props.link ? (
        <Link href={props.link} preserveState={true}>
          {normalModeCard}
        </Link>
      ) : (
        normalModeCard
      )}
    </div>
  );
}
